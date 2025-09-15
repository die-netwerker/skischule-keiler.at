function addScrolled() {
    let sp = window.scrollY;
    if (sp >= 50) {
        document.querySelector("header.header-main").classList.add("scroll");
    } else {
        document.querySelector("header.header-main").classList.remove("scroll");
    }
}
addScrolled();
window.addEventListener("scroll", function () {
    addScrolled();
});