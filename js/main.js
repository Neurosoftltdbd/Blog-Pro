/* Blog Pro — minimal vanilla JS, no dependencies. */
(function () {
  "use strict";

  // Main mobile menu toggle
  var menuToggle = document.querySelector(".nav-toggle");
  var mobileMenu = document.querySelector("#mobile-menu");

  if (menuToggle && mobileMenu) {
    menuToggle.addEventListener("click", function () {
      var isHidden = mobileMenu.classList.toggle("hidden");
      menuToggle.setAttribute("aria-expanded", String(!isHidden));
    });
  }

  // Mobile submenu toggles
  var submenuToggles = document.querySelectorAll('.mobile-nav .submenu-toggle');

  submenuToggles.forEach(function(toggle) {
    var subMenu = toggle.closest('.menu-item-has-children').querySelector('.sub-menu');

    if (subMenu) {
      toggle.addEventListener('click', function() {
        var isExpanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', String(!isExpanded));
        subMenu.classList.toggle('hidden');

        var iconSvg = this.querySelector('svg');
        if (iconSvg) {
          iconSvg.style.transform = !isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
        }
      });
    }
  });

  // Lazy loading for images/iframes
  if ("IntersectionObserver" in window) {
    var lazyTargets = document.querySelectorAll("img:not([loading]), iframe:not([loading])");
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.setAttribute("loading", "lazy");
          observer.unobserve(entry.target);
        }
      });
    });
    lazyTargets.forEach(function (el) {
      observer.observe(el);
    });
  }
})();
