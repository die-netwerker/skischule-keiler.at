<?php declare(strict_types=1);

namespace NetwerkCustomFields;

use NetwerkCustomFields\Service\CustomFieldsInstaller;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class NetwerkCustomFields extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this->getInstaller()->install($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->getInstaller()->uninstall($uninstallContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        // Falls Relationen fehlen: idempotent sicherstellen
        $this->getInstaller()->addRelations($activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        // nichts nötig
    }

    public function update(UpdateContext $updateContext): void
    {
        // optional: Migrationslogik
    }

    private function getInstaller(): CustomFieldsInstaller
    {
        if ($this->container->has(CustomFieldsInstaller::class)) {
            return $this->container->get(CustomFieldsInstaller::class);
        }

        // Fallback, falls DI (services.xml) nicht greift
        return new CustomFieldsInstaller(
            $this->container->get('custom_field_set.repository'),
            $this->container->get('custom_field_set_relation.repository'),
            $this->container->get('custom_field.repository')
        );
    }
}
