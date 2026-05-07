document.addEventListener("DOMContentLoaded", () => {
  const faviconHref = "/Images/bfi-new-logo.svg";
  const ensureFavicon = () => {
    const existingLinks = document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"]');
    if (existingLinks.length) {
      existingLinks.forEach((link) => {
        link.setAttribute("href", faviconHref);
        if (link.getAttribute("rel") === "icon") {
          link.setAttribute("type", "image/svg+xml");
        }
      });
      return;
    }

    const icon = document.createElement("link");
    icon.rel = "icon";
    icon.type = "image/svg+xml";
    icon.href = faviconHref;
    document.head.appendChild(icon);

    const shortcut = document.createElement("link");
    shortcut.rel = "shortcut icon";
    shortcut.href = faviconHref;
    document.head.appendChild(shortcut);
  };

  document.querySelectorAll(".brand-mark").forEach((mark) => {
    if (mark.querySelector("img")) return;

    const legacyIcon = mark.querySelector("svg");
    if (!legacyIcon) return;

    legacyIcon.remove();
    const logo = document.createElement("img");
    logo.src = faviconHref;
    logo.alt = "Bold Footprint Initiatives logo";
    mark.appendChild(logo);
  });

  ensureFavicon();

  document.querySelectorAll(".nav-toggle, [data-nav-toggle]").forEach((button) => {
    const hasSpans = button.querySelectorAll("span").length >= 3;
    const text = (button.textContent || "").trim().toLowerCase();
    if (!hasSpans && (text === "menu" || text.includes("menu"))) {
      button.textContent = "";
      for (let i = 0; i < 3; i += 1) {
        button.appendChild(document.createElement("span"));
      }
    }
    if (!button.getAttribute("aria-label")) {
      button.setAttribute("aria-label", "Toggle navigation menu");
    }
    if (!button.getAttribute("aria-expanded")) {
      button.setAttribute("aria-expanded", "false");
    }
  });

  const nav = document.querySelector("[data-nav]");
  const toggle = document.querySelector("[data-nav-toggle]");
  const mobileMenu = document.querySelector("[data-mobile-menu]");

  const syncNav = () => {
    if (!nav) return;
    nav.classList.toggle("scrolled", window.scrollY > 48);
  };

  syncNav();
  window.addEventListener("scroll", syncNav, { passive: true });

  if (toggle && mobileMenu) {
    if (!toggle.getAttribute("aria-label")) {
      toggle.setAttribute("aria-label", "Toggle navigation menu");
    }

    const closeMenu = () => {
      mobileMenu.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    };

    toggle.addEventListener("click", () => {
      const isOpen = mobileMenu.classList.toggle("open");
      toggle.setAttribute("aria-expanded", String(isOpen));
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeMenu();
      }
    });

    mobileMenu.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", closeMenu);
    });
  }

  document.querySelectorAll("[data-tab-group]").forEach((group) => {
    const buttons = group.querySelectorAll("[data-tab-target]");
    const panels = group.querySelectorAll("[data-tab-panel]");

    const activate = (id) => {
      buttons.forEach((button) => {
        const isActive = button.dataset.tabTarget === id;
        button.classList.toggle("active", isActive);
        button.setAttribute("aria-selected", String(isActive));
      });

      panels.forEach((panel) => {
        panel.hidden = panel.dataset.tabPanel !== id;
      });
    };

    const hashId = window.location.hash.replace(/^#/, "");
    const initial =
      (hashId && [...panels].some((panel) => panel.dataset.tabPanel === hashId) ? hashId : null) ||
      [...buttons].find((button) => button.classList.contains("active"))?.dataset.tabTarget ||
      buttons[0]?.dataset.tabTarget;
    if (initial) activate(initial);

    buttons.forEach((button) => {
      button.addEventListener("click", () => {
        const targetId = button.dataset.tabTarget;
        activate(targetId);

        if (group.dataset.tabSyncHash === "true") {
          history.replaceState(null, "", `#${targetId}`);
        }

        const scrollSelector = button.dataset.tabScrollTarget || group.dataset.tabScrollTarget;
        if (scrollSelector) {
          const scrollTarget = document.querySelector(scrollSelector);
          scrollTarget?.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    });
  });

  document.querySelectorAll("[data-accordion-item]").forEach((item) => {
    const button = item.querySelector("[data-accordion-button]");
    const panel = item.querySelector("[data-accordion-panel]");
    if (!button || !panel) return;

    const open = item.classList.contains("is-open");
    button.setAttribute("aria-expanded", String(open));
    panel.hidden = !open;

    button.addEventListener("click", () => {
      const isOpen = item.classList.toggle("is-open");
      button.setAttribute("aria-expanded", String(isOpen));
      panel.hidden = !isOpen;
    });
  });

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12 }
  );

  document.querySelectorAll(".reveal").forEach((element) => observer.observe(element));
});
