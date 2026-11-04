
(() => {
  const slider = document.querySelector(
    '[data-mx-banner-slider]'
  );

  if (slider) {
    const slides = [
      ...slider.querySelectorAll('[data-mx-slide]')
    ];

    let current = 0;

    const show = (next) => {
      if (!slides.length) return;

      current = (next + slides.length) % slides.length;

      slides.forEach((el, i) =>
        el.classList.toggle('hidden', i !== current)
      );
    };

    slider.querySelector('[data-mx-prev]')
      ?.addEventListener('click', () =>
        show(current - 1)
      );

    slider.querySelector('[data-mx-next]')
      ?.addEventListener('click', () =>
        show(current + 1)
      );

    if (slides.length > 1) {
      setInterval(() => show(current + 1), 5500);
    }
  }

  const clock = document.getElementById('mx-flash-clock');

  if (clock) {
    const initial = Math.max(
      0,
      Number(clock.dataset.seconds) || 0
    );

    const startedAt = Date.now();
    let finished = false;

    const draw = () => {
      const left = Math.max(
        0,
        initial - Math.floor(
          (Date.now() - startedAt) / 1000
        )
      );

      const h = Math.floor(left / 3600);
      const m = Math.floor((left % 3600) / 60);
      const s = left % 60;

      clock.textContent = [h, m, s]
        .map(v => String(v).padStart(2, '0'))
        .join(':');

      if (left === 0 && !finished) {
        finished = true;
        location.reload();
      }
    };

    draw();
    setInterval(draw, 1000);
  }
})();
