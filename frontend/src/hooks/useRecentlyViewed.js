// src/hooks/useRecentlyViewed.js
// Tracks recently viewed products in localStorage with a max cap.

import { useState, useEffect } from 'react';

const KEY = 'mobiex_recently_viewed';
const MAX = 10;

export function useRecentlyViewed() {
  const [viewed, setViewed] = useState(() => {
    try { return JSON.parse(localStorage.getItem(KEY)) || []; }
    catch { return []; }
  });

  const addViewed = (product) => {
    setViewed(prev => {
      const filtered = prev.filter(p => p.id !== product.id);
      const next = [
        {
          id: product.id,
          name: product.name,
          slug: product.slug,
          price: product.price ?? product.min_price ?? product.base_price,
          primary_image: product.primary_image ?? product.thumbnail ?? product.primary_image_url ?? null,
        },
        ...filtered,
      ].slice(0, MAX);
      localStorage.setItem(KEY, JSON.stringify(next));
      return next;
    });
  };

  return { viewed, addViewed };
}
