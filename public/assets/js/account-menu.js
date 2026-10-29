document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("header button").forEach((button) => {
    if (!button.querySelector("img")) return;
    const menu = button.nextElementSibling;
    if (!menu) return;
    const wrapper = button.parentElement;

    button.setAttribute("aria-expanded", "false");
    button.setAttribute("aria-haspopup", "true");

    const setOpen = (open) => {
      button.setAttribute("aria-expanded", String(open));
      menu.classList.toggle("opacity-0", !open);
      menu.classList.toggle("pointer-events-none", !open);
    };

    wrapper.addEventListener("mouseenter", () => setOpen(true));
    wrapper.addEventListener("mouseleave", () => {
      if (button.dataset.pinned !== "true") setOpen(false);
    });

    button.addEventListener("click", (event) => {
      event.stopPropagation();
      const pin = button.dataset.pinned !== "true";
      button.dataset.pinned = String(pin);
      setOpen(pin);
    });

    document.addEventListener("click", (event) => {
      if (button.contains(event.target) || menu.contains(event.target)) return;
      button.dataset.pinned = "false";
      setOpen(false);
    });
  });
});
