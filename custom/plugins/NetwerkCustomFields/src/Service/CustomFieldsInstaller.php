<?php declare(strict_types=1);

namespace NetwerkCustomFields\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Idempotenter Installer für mehrere Sets (z. B. product, customer).
 * - Sets per Name finden/erzeugen
 * - Felder per Name finden/erzeugen (NICHT inline im Set!)
 * - Relation nur anlegen, wenn nicht vorhanden (kompatibel zu customFieldSetId/setId)
 */
class CustomFieldsInstaller
{
    /**
     * Definiere hier beliebig viele Sets & Felder.
     * Wichtig: Feldnamen sind global unique -> Prefixe verwenden!
     */
    private const SETS = [
        // ====== Produkt-Set ======
        [
            'name'       => 'nw_product',
            'entity'     => 'product',
            'label'      => [
                'de-DE' => 'Zusätzliche Produktinfos',
                'en-GB' => 'Additional product infos',
                Defaults::LANGUAGE_SYSTEM => 'Zusätzliche Produktinfos',
            ],
            'fields'     => [
                [
                    'name'     => 'nw_product_subtitle',
                    'type'     => CustomFieldTypes::TEXT,
                    'position' => 1,
                    'label'    => [
                        'de-DE' => 'Untertitel',
                        'en-GB' => 'Subtitle',
                        Defaults::LANGUAGE_SYSTEM => 'Untertitel',
                    ],
                ],
                [
                    'name'     => 'nw_product_short_description',
                    'type'     => CustomFieldTypes::TEXT,
                    'position' => 2,
                    'label'    => [
                        'de-DE' => 'Kurzbeschreibung',
                        'en-GB' => 'Short description',
                        Defaults::LANGUAGE_SYSTEM => 'Kurzbeschreibung',
                    ],
                ],
            ],
        ],
    ];

    /** @var EntityRepository */
    private $customFieldSetRepository;
    /** @var EntityRepository */
    private $customFieldSetRelationRepository;
    /** @var EntityRepository */
    private $customFieldRepository;

    public function __construct(
        EntityRepository $customFieldSetRepository,
        EntityRepository $customFieldSetRelationRepository,
        EntityRepository $customFieldRepository
    ) {
        $this->customFieldSetRepository          = $customFieldSetRepository;
        $this->customFieldSetRelationRepository  = $customFieldSetRelationRepository;
        $this->customFieldRepository             = $customFieldRepository;
    }

    public function install(Context $context): void
    {
        foreach (self::SETS as $def) {
            $setId = $this->ensureSet($context, $def['name'], $def['label']);

            // Felder (einzeln) idempotent anlegen/aktualisieren
            foreach ($def['fields'] as $field) {
                $this->ensureField(
                    $context,
                    $setId,
                    $field['name'],
                    $field['type'],
                    $field['label'],
                    isset($field['position']) ? (int)$field['position'] : 1,
                    isset($field['options']) ? $field['options'] : null
                );
            }

            // Relation zum Entity idempotent
            $this->ensureRelation($context, $setId, $def['entity']);
        }
    }

    public function addRelations(Context $context): void
    {
        foreach (self::SETS as $def) {
            $setId = $this->getSetIdByName($context, $def['name']);
            if ($setId) {
                $this->ensureRelation($context, $setId, $def['entity']);
            }
        }
    }

    public function uninstall(Context $context): void
    {
        foreach (self::SETS as $def) {
            $setId = $this->getSetIdByName($context, $def['name']);
            if (!$setId) {
                continue;
            }

            // Relationen löschen
            $this->deleteRelationsBySetId($context, $setId);

            // Felder (per Name) löschen
            foreach ($def['fields'] as $field) {
                $this->deleteFieldByName($context, $field['name']);
            }

            // Set löschen
            $this->customFieldSetRepository->delete([['id' => $setId]], $context);
        }
    }
    // ===== Helpers =====

    private function ensureSet(Context $context, string $name, array $label): string
    {
        $existing = $this->customFieldSetRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('name', $name)),
            $context
        )->first();

        $id = $existing ? $existing->getId() : Uuid::randomHex();

        $this->customFieldSetRepository->upsert([[
            'id'     => $id,
            'name'   => $name,
            'config' => ['label' => $label],
        ]], $context);

        return $id;
    }

    private function ensureField(
        Context $context,
        string $setId,
        string $name,
        string $type,
        array $label,
        int $position = 1,
        ?array $options = null
    ): void {
        // per Name suchen -> bestehende ID wiederverwenden
        $existing = $this->customFieldRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('name', $name)),
            $context
        )->first();

        $id = $existing ? $existing->getId() : Uuid::randomHex();

        $base = [
            'id'      => $id,
            'name'    => $name,
            'type'    => $type,
            'active'  => true,
            'config'  => [
                'label'               => $label,
                'customFieldPosition' => $position,
            ],
        ];
        if ($options && $type === CustomFieldTypes::SELECT) {
            $base['config']['options'] = $options;
        }

        // 1. Versuch: property customFieldSetId (Standard)
        try {
            $payload = $base;
            $payload['customFieldSetId'] = $setId;
            $this->customFieldRepository->upsert([$payload], $context);
            return;
        } catch (UnmappedFieldException $e) {
            // 2. Fallback: setId (ältere/abweichende Mappings)
            $payload = $base;
            $payload['setId'] = $setId;
            $this->customFieldRepository->upsert([$payload], $context);
        }
    }

    private function ensureRelation(Context $context, string $setId, string $entityName): void
    {
        // Suche + Insert mit beiden möglichen Property-Namen
        try {
            $crit = (new Criteria())
                ->addFilter(new EqualsFilter('customFieldSetId', $setId))
                ->addFilter(new EqualsFilter('entityName', $entityName));
            $exists = $this->customFieldSetRelationRepository->search($crit, $context)->first();

            if (!$exists) {
                $this->customFieldSetRelationRepository->create([[
                    'id'               => Uuid::randomHex(),
                    'customFieldSetId' => $setId,
                    'entityName'       => $entityName,
                ]], $context);
            }
        } catch (UnmappedFieldException $e) {
            $crit = (new Criteria())
                ->addFilter(new EqualsFilter('setId', $setId))
                ->addFilter(new EqualsFilter('entityName', $entityName));
            $exists = $this->customFieldSetRelationRepository->search($crit, $context)->first();

            if (!$exists) {
                $this->customFieldSetRelationRepository->create([[
                    'id'         => Uuid::randomHex(),
                    'setId'      => $setId,
                    'entityName' => $entityName,
                ]], $context);
            }
        }
    }

    private function getSetIdByName(Context $context, string $name): ?string
    {
        $existing = $this->customFieldSetRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('name', $name)),
            $context
        )->first();

        return $existing ? $existing->getId() : null;
    }

    private function deleteRelationsBySetId(Context $context, string $setId): void
    {
        // IDs über beide Varianten ermitteln
        try {
            $ids = $this->customFieldSetRelationRepository->searchIds(
                (new Criteria())->addFilter(new EqualsFilter('customFieldSetId', $setId)),
                $context
            )->getIds();
        } catch (UnmappedFieldException $e) {
            $ids = $this->customFieldSetRelationRepository->searchIds(
                (new Criteria())->addFilter(new EqualsFilter('setId', $setId)),
                $context
            )->getIds();
        }

        if ($ids) {
            $this->customFieldSetRelationRepository->delete(
                array_map(static fn ($id) => ['id' => $id], $ids),
                $context
            );
        }
    }

    private function deleteFieldByName(Context $context, string $name): void
    {
        $existing = $this->customFieldRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('name', $name)),
            $context
        )->first();

        if ($existing) {
            $this->customFieldRepository->delete([['id' => $existing->getId()]], $context);
        }
    }
}
