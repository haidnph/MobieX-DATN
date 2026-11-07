
function switchVariant(type, value, el) {
  if (type === 'color') {
    document.querySelectorAll('.swatch-btn').forEach(b => {
      b.classList.remove(
        'bg-surface-bright',
        'shadow-[0_0_16px_rgba(77,142,255,0.4)]'
      );
      b.classList.add('bg-surface-container-low');
    });

    el.classList.add(
      'bg-surface-bright',
      'shadow-[0_0_16px_rgba(77,142,255,0.4)]'
    );
    document.getElementById('current-color-label').innerText = value;

  } else if (type === 'storage') {
    document.querySelectorAll('.storage-pill').forEach(b => {
      b.classList.remove(
        'bg-primary-container',
        'text-on-primary-container',
        'shadow-[0_0_16px_rgba(77,142,255,0.35)]'
      );
      b.classList.add(
        'bg-surface-container-high',
        'text-on-surface'
      );
    });

    el.classList.remove(
      'bg-surface-container-high',
      'text-on-surface'
    );
    el.classList.add(
      'bg-primary-container',
      'text-on-primary-container',
      'shadow-[0_0_16px_rgba(77,142,255,0.35)]'
    );

    let price = "$1,199.00";
    let original = "$1,299.00";

    if (value === '512GB') {
      price = "$1,399.00";
      original = "$1,499.00";
    }

    if (value === '1TB') {
      price = "$1,599.00";
      original = "$1,699.00";
    }

    document.getElementById('product-price').innerText = price;
    document.getElementById('product-original-price').innerText = original;
    document.getElementById('current-storage-label').innerText = value;
  }
}

function switchThumb(thumbIndex, el) {
  document.querySelectorAll('.thumb-item').forEach(t => {
    t.classList.remove(
      'ring-2',
      'ring-primary-container',
      'shadow-[0_0_16px_rgba(77,142,255,0.45)]'
    );
  });

  el.classList.add(
    'ring-2',
    'ring-primary-container',
    'shadow-[0_0_16px_rgba(77,142,255,0.45)]'
  );

  const captions = [
    "iPhone 15 Pro Max - Natural Titanium Aerodynamic Frontal Array",
    "Quad Spectral Computational Camera Subsystem with Periscope Prism",
    "Grade 5 Brushed Titanium Chamfered Ergonomic Frame Architecture",
    "High-Speed 10Gbps USB-C 3 Port with Reversible Direct DisplayPort",
    "Edge-to-Edge 120Hz ProMotion Super Retina XDR OLED Matrix"
  ];

  const caption = document.getElementById('img-caption');

  if (
    caption &&
    Number.isInteger(thumbIndex) &&
    thumbIndex >= 0 &&
    thumbIndex < captions.length
  ) {
    caption.innerText = captions[thumbIndex];
  }
}

function changeQty(delta) {
  const input = document.getElementById('sku-qty');
  if (!input) return;

  const current = Number.parseInt(input.value, 10);
  const base = Number.isFinite(current) ? current : 1;

  const change = Number.isFinite(Number(delta))
    ? Number(delta)
    : 0;

  input.value = Math.max(
    1,
    Math.min(8, base + change)
  );
}
