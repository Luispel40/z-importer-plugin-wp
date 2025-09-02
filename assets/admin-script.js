document.addEventListener("DOMContentLoaded", function () {
    const container = document.querySelector(".wrap.html-zip-importer");
    if (!container) return;

    const buttons = container.querySelectorAll(".tab-button");
    const contents = container.querySelectorAll(".tab-content");

    buttons.forEach((btn) => {
        btn.addEventListener("click", () => {
            buttons.forEach(b => b.classList.remove("active"));
            contents.forEach(c => c.classList.remove("active"));

            btn.classList.add("active");
            const tabId = btn.getAttribute("data-tab");
            const activeContent = container.querySelector(`#${tabId}`);
            if (activeContent) activeContent.classList.add("active");
        });
    });
});
