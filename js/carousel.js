/**
 * @file
 * Initializes the IIIF carousel behavior.
 */
(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.iiifCarousel = {
    attach: function (context, settings) {
      // Get duration from drupalSettings, with a fallback to 10 seconds.
      const duration = drupalSettings.iiif_random_block?.carousel?.duration || 10000;

      // Find all carousel containers in the current context that haven't been processed yet.
      const carousels = context.querySelectorAll('.iiif-carousel-container:not(.iiif-carousel-processed)');

      carousels.forEach(function (carousel) {
        // Mark as processed to prevent re-attaching the behavior.
        carousel.classList.add('iiif-carousel-processed');

        const slides = carousel.getElementsByClassName('iiif-carousel-item');
        if (slides.length === 0) {
          return;
        }

        // Store state on the element itself.
        carousel.slideIndex = 0;

        // Prepare images for smooth first render: add is-loaded when image completes.
        const imgs = carousel.querySelectorAll('.iiif-carousel-item img');
        imgs.forEach((img, idx) => {
          const markLoaded = () => img.classList.add('is-loaded');
          if (img.complete && img.naturalWidth > 0) {
            // Already loaded from cache
            markLoaded();
          } else {
            img.addEventListener('load', markLoaded, { once: true });
            img.addEventListener('error', () => img.classList.add('is-loaded'), { once: true });
          }
          // Hint: make the first image eager to reduce flash on first cycle.
          if (idx === 0) {
            img.setAttribute('loading', 'eager');
            img.setAttribute('decoding', 'async');
          }
        });

        function showSlides() {
          // If the element is no longer in the DOM, stop the loop.
          if (!document.body.contains(carousel)) {
            return;
          }

          for (let i = 0; i < slides.length; i++) {
            slides[i].classList.remove('active');
          }

          carousel.slideIndex++;
          if (carousel.slideIndex > slides.length) {
            carousel.slideIndex = 1;
          }

          if (slides[carousel.slideIndex - 1]) {
            slides[carousel.slideIndex - 1].classList.add('active');
          }

          // Call this function again after the configured duration
          setTimeout(showSlides, duration);
        }

        // Start the slideshow for this instance after ensuring first image has had a tick to apply CSS.
        // This helps trigger the .active animation on initial render.
        setTimeout(showSlides, 0);
      });
    }
  };

})(Drupal, drupalSettings);
