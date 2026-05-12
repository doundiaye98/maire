const menuToggle = document.getElementById("menuToggle");
const mainNav = document.getElementById("mainNav");
const themeToggle = document.getElementById("themeToggle");
const backToTop = document.getElementById("backToTop");
const pageLoader = document.getElementById("pageLoader");
const storageThemeKey = "mairie-theme";

if (menuToggle && mainNav) {
  menuToggle.addEventListener("click", () => {
    mainNav.classList.toggle("open");
  });
}

if (themeToggle) {
  const savedTheme = localStorage.getItem(storageThemeKey);
  if (savedTheme === "dark") {
    document.documentElement.setAttribute("data-theme", "dark");
  }

  themeToggle.addEventListener("click", () => {
    const current = document.documentElement.getAttribute("data-theme");
    const next = current === "dark" ? "light" : "dark";
    document.documentElement.setAttribute("data-theme", next);
    localStorage.setItem(storageThemeKey, next);
  });
}

window.addEventListener("load", () => {
  document.body.classList.add("page-ready");
  if (pageLoader) {
    pageLoader.classList.add("hidden");
  }
});

if (backToTop) {
  window.addEventListener("scroll", () => {
    if (window.scrollY > 260) {
      backToTop.classList.add("show");
    } else {
      backToTop.classList.remove("show");
    }
  });

  backToTop.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
}

const revealTargets = document.querySelectorAll(
  ".card, .section-title, .services-hero h1, .detail-hero h1, .hero:not(.portal-hero) h1, .hero:not(.portal-hero) p, .featured-head"
);

if (revealTargets.length > 0) {
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("revealed");
        }
      });
    },
    { threshold: 0.15 }
  );

  revealTargets.forEach((target) => {
    target.classList.add("reveal");
    if (target.classList.contains("card")) {
      const index = Array.from(document.querySelectorAll(".card")).indexOf(target);
      target.style.transitionDelay = `${Math.min(index * 35, 320)}ms`;
    }
    observer.observe(target);
  });
}

const standardSearch = document.getElementById("standardSearch");
if (standardSearch) {
  standardSearch.addEventListener("input", () => {
    const value = standardSearch.value.trim().toLowerCase();
    const searchable = document.querySelectorAll("[data-search]");
    searchable.forEach((item) => {
      const haystack = (item.getAttribute("data-search") || "").toLowerCase();
      item.style.display = haystack.includes(value) ? "" : "none";
    });
  });
}

const servicesTable = document.getElementById("servicesTable");
const prevPageBtn = document.getElementById("prevPageBtn");
const nextPageBtn = document.getElementById("nextPageBtn");
const pageIndicator = document.getElementById("pageIndicator");
const exportCsvBtn = document.getElementById("exportCsvBtn");
const exportPdfBtn = document.getElementById("exportPdfBtn");

if (servicesTable) {
  const rows = Array.from(servicesTable.querySelectorAll("tbody tr"));
  let page = 1;
  const pageSize = 3;
  let sortAsc = true;

  const renderTablePage = () => {
    const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
    if (page > totalPages) page = totalPages;
    rows.forEach((row, idx) => {
      const visible = idx >= (page - 1) * pageSize && idx < page * pageSize;
      row.style.display = visible ? "" : "none";
    });
    if (pageIndicator) pageIndicator.textContent = `Page ${page} / ${totalPages}`;
    if (prevPageBtn) prevPageBtn.disabled = page === 1;
    if (nextPageBtn) nextPageBtn.disabled = page === totalPages;
  };

  const sortByColumn = (col) => {
    rows.sort((a, b) => {
      const aVal = (a.children[col]?.textContent || "").trim().toLowerCase();
      const bVal = (b.children[col]?.textContent || "").trim().toLowerCase();
      return sortAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    });
    sortAsc = !sortAsc;
    const tbody = servicesTable.querySelector("tbody");
    rows.forEach((row) => tbody?.appendChild(row));
    page = 1;
    renderTablePage();
  };

  document.querySelectorAll(".sort-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const col = Number(btn.getAttribute("data-sort-col") || 0);
      sortByColumn(col);
    });
  });

  if (prevPageBtn) {
    prevPageBtn.addEventListener("click", () => {
      page = Math.max(1, page - 1);
      renderTablePage();
    });
  }

  if (nextPageBtn) {
    nextPageBtn.addEventListener("click", () => {
      const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
      page = Math.min(totalPages, page + 1);
      renderTablePage();
    });
  }

  if (exportCsvBtn) {
    exportCsvBtn.addEventListener("click", () => {
      const header = ["Service", "Point de service", "Horaires"];
      const lines = [header.join(",")];
      rows.forEach((row) => {
        const cols = Array.from(row.children).map((cell) =>
          `"${(cell.textContent || "").trim().replace(/"/g, '""')}"`
        );
        lines.push(cols.join(","));
      });
      const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.download = "services-standard-plus.csv";
      link.click();
      URL.revokeObjectURL(link.href);
    });
  }

  if (exportPdfBtn) {
    exportPdfBtn.addEventListener("click", () => {
      window.print();
    });
  }

  renderTablePage();
}

const standardTabs = document.querySelectorAll(".standard-tab");
if (standardTabs.length > 0) {
  standardTabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      const tabName = tab.getAttribute("data-tab");
      standardTabs.forEach((item) => item.classList.remove("active"));
      tab.classList.add("active");
      document.querySelectorAll(".standard-tab-panel").forEach((panel) => {
        panel.classList.toggle("active", panel.getAttribute("data-panel") === tabName);
      });
    });
  });
}

const counters = document.querySelectorAll("[data-counter]");
if (counters.length > 0) {
  const animateCounter = (element) => {
    const target = Number(element.getAttribute("data-counter") || 0);
    let value = 0;
    const step = Math.max(1, Math.floor(target / 20));
    const timer = window.setInterval(() => {
      value += step;
      if (value >= target) {
        value = target;
        window.clearInterval(timer);
      }
      element.textContent = String(value);
    }, 30);
  };

  const counterObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting && !entry.target.classList.contains("counted")) {
          entry.target.classList.add("counted");
          animateCounter(entry.target);
        }
      });
    },
    { threshold: 0.35 }
  );

  counters.forEach((counter) => counterObserver.observe(counter));
}

const internalLinks = document.querySelectorAll('a[href$=".php"]');
internalLinks.forEach((link) => {
  link.addEventListener("click", (event) => {
    const href = link.getAttribute("href") || "";
    if (href.startsWith("http")) return;
    if (event.ctrlKey || event.metaKey) return;

    event.preventDefault();
    document.body.classList.remove("page-ready");
    if (pageLoader) {
      pageLoader.classList.remove("hidden");
    }
    window.setTimeout(() => {
      window.location.href = href;
    }, 180);
  });
});

const reduceMotionQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
const supportsFinePointer = window.matchMedia("(pointer: fine)").matches;

const init3DCards = () => {
  if (reduceMotionQuery.matches || !supportsFinePointer) return;

  const cards3D = document.querySelectorAll(
    ".card, .service-tile, .quick-card, .featured-project-card, .project-card"
  );

  cards3D.forEach((card) => {
    card.classList.add("is-3d-ready");

    const onMove = (event) => {
      const rect = card.getBoundingClientRect();
      const px = (event.clientX - rect.left) / rect.width;
      const py = (event.clientY - rect.top) / rect.height;

      const rotateY = (px - 0.5) * 10;
      const rotateX = (0.5 - py) * 10;
      const glowX = px * 100;
      const glowY = py * 100;

      card.style.setProperty("--tilt-x", `${rotateX.toFixed(2)}deg`);
      card.style.setProperty("--tilt-y", `${rotateY.toFixed(2)}deg`);
      card.style.setProperty("--glow-x", `${glowX.toFixed(2)}%`);
      card.style.setProperty("--glow-y", `${glowY.toFixed(2)}%`);
    };

    const onLeave = () => {
      card.style.setProperty("--tilt-x", "0deg");
      card.style.setProperty("--tilt-y", "0deg");
      card.style.setProperty("--glow-x", "50%");
      card.style.setProperty("--glow-y", "50%");
    };

    card.addEventListener("mousemove", onMove);
    card.addEventListener("mouseleave", onLeave);
  });
};

const initHeroParallax = () => {
  if (reduceMotionQuery.matches || !supportsFinePointer) return;

  const heroes = document.querySelectorAll(".hero, .detail-hero, .services-hero");
  if (heroes.length === 0) return;

  window.addEventListener(
    "mousemove",
    (event) => {
      const xRatio = event.clientX / window.innerWidth - 0.5;
      const yRatio = event.clientY / window.innerHeight - 0.5;

      heroes.forEach((hero) => {
        hero.style.setProperty("--hero-pan-x", `${(xRatio * 10).toFixed(2)}px`);
        hero.style.setProperty("--hero-pan-y", `${(yRatio * 8).toFixed(2)}px`);
      });
    },
    { passive: true }
  );
};

init3DCards();
initHeroParallax();
