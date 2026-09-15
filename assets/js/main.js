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

  // Social share links open in a small popup window.
  // mailto: links (email share) must NOT be intercepted — open them normally
  // via the default anchor behavior, so we bail before preventDefault().
  document.querySelectorAll("[data-share-popup]").forEach(function (link) {
    link.addEventListener("click", function (event) {
      var url = link.getAttribute("href");
      if (!url || url.indexOf("mailto:") === 0) {
        return; // mailto and missing URLs open normally
      }
      event.preventDefault();
      window.open(
        url,
        "share-" + (link.getAttribute("data-share-platform") || "share"),
        "width=600,height=540,left=" + ((window.screen.width - 600) / 2) + ",top=" + ((window.screen.height - 540) / 2) + ",noopener,noreferrer"
      );
    });
  });

  // Copy link button with fallback + toast feedback
  var copyButton = document.querySelector("[data-share-copy]");
  if (copyButton) {
    var toastTimer = null;
    var toast = null;

    var showToast = function (message) {
      if (!toast) {
        toast = document.createElement("div");
        toast.className = "share-toast";
        toast.setAttribute("role", "status");
        document.body.appendChild(toast);
      }
      toast.textContent = message;
      toast.classList.add("is-visible");
      clearTimeout(toastTimer);
      toastTimer = setTimeout(function () {
        toast.classList.remove("is-visible");
      }, 2500);
    };

    copyButton.addEventListener("click", function () {
      var msg = copyButton.getAttribute("data-copy-msg") || "Link copied!";
      var failMsg = copyButton.getAttribute("data-copy-fail") || "Could not copy link";
      var done = function () {
        showToast(msg);
      };
      var fail = function () {
        showToast(failMsg);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(window.location.href).then(done, fail);
      } else {
        // Legacy fallback: hidden textarea + execCommand
        var ta = document.createElement("textarea");
        ta.value = window.location.href;
        ta.setAttribute("readonly", "");
        ta.style.position = "fixed";
        ta.style.opacity = "0";
        document.body.appendChild(ta);
        ta.select();
        try {
          document.execCommand("copy") ? done() : fail();
        } catch (e) {
          fail();
        }
        document.body.removeChild(ta);
      }
    });
  }

  /* ------------------------------------------------------------------
   * Back-to-top + reading progress bar (markup in footer.php).
   * Both start hidden; a single passive scroll listener (rAF-throttled)
   * drives them. Smooth scroll is skipped under prefers-reduced-motion.
   * ---------------------------------------------------------------- */
  var backTop = document.getElementById("blogpro-back-top");
  var progressWrap = document.getElementById("blogpro-scroll-progress");
  var progressBar = document.getElementById("blogpro-scroll-progress-bar");

  if (backTop || progressBar) {
    var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var ticking = false;

    var onScroll = function () {
      ticking = false;
      var doc = document.documentElement;
      var top = window.scrollY || doc.scrollTop || 0;
      var total = doc.scrollHeight - window.innerHeight;

      if (backTop) {
        var show = top > 600 && total > 200;
        if (show === backTop.hidden) {
          backTop.hidden = !show;
          backTop.tabIndex = show ? 0 : -1;
        }
        if (show) {
          backTop.classList.remove("opacity-0", "pointer-events-none", "translate-y-2");
        } else {
          backTop.classList.add("opacity-0", "pointer-events-none", "translate-y-2");
        }
      }

      if (progressWrap && progressBar) {
        var pct = total > 0 ? Math.min(100, Math.max(0, (top / total) * 100)) : 0;
        if (pct > 2 && progressWrap.hidden) {
          progressWrap.hidden = false;
        } else if (pct <= 2 && !progressWrap.hidden) {
          progressWrap.hidden = true;
        }
        progressBar.style.width = pct + "%";
      }
    };

    window.addEventListener(
      "scroll",
      function () {
        if (!ticking) {
          ticking = true;
          window.requestAnimationFrame(onScroll);
        }
      },
      { passive: true }
    );
    onScroll();

    if (backTop) {
      backTop.addEventListener("click", function (e) {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" });
        // Return focus to the skip-link target for keyboard users.
        var main = document.getElementById("main");
        if (main) {
          main.setAttribute("tabindex", "-1");
          main.focus({ preventScroll: true });
        }
        if (history.replaceState) {
          history.replaceState(null, "", window.location.pathname + window.location.search);
        }
      });
    }
  }
})();
