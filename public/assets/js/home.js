(function initFlashSaleTimer() {
      let totalSeconds = (4 * 3600) + (28 * 60) + 15;
      const elHours = document.getElementById('timer-hours');
      const elMinutes = document.getElementById('timer-minutes');
      const elSeconds = document.getElementById('timer-seconds');

      if (!elHours || !elMinutes || !elSeconds) return;

      const interval = setInterval(() => {
        if (totalSeconds <= 0) {
          clearInterval(interval);
          return;
        }
        totalSeconds--;
        const h = Math.floor(totalSeconds / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = totalSeconds % 60;

        elHours.textContent = h < 10 ? '0' + h : h;
        elMinutes.textContent = m < 10 ? '0' + m : m;
        elSeconds.textContent = s < 10 ? '0' + s : s;
      }, 1000);
    })();