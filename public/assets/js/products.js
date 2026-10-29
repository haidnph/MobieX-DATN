(function initProductFilters() {
    const clearBtn = document.getElementById('clear-all-filters');
    const chipContainer = document.getElementById('active-filter-chips');
    const compareBar = document.getElementById('compare-bar');

    if (clearBtn && chipContainer) {
      clearBtn.addEventListener('click', () => {
        chipContainer.innerHTML = '<span class="font-label-spec text-label-spec text-outline">No active filters</span>';
      });
    }

    // Toggle Compare Bar visibility based on interactive checkboxes
    const compareBoxes = document.querySelectorAll('input[type="checkbox"][title*="Compare"], input[type="checkbox"]');
    compareBoxes.forEach(box => {
      box.addEventListener('change', () => {
        const checkedCount = document.querySelectorAll('input[type="checkbox"]:checked').length;
        if (compareBar) {
          compareBar.style.transform = checkedCount > 0 ? 'translateX(-50%) translateY(0)' : 'translateX(-50%) translateY(120%)';
        }
      });
    });
  })();