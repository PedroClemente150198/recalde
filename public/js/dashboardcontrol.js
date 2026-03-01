

// Get the sidebar, close button, and search button elements
let sidebar = document.querySelector(".sidebar");
let closeBtn = document.querySelector("#btn");
let searchBtn = document.querySelector(".bx-search");
let logoutBtn = document.querySelector("#log_out");
let mobileNavToggle = document.querySelector("#mobile-nav-toggle");
let mobileNavToggleIcon = mobileNavToggle?.querySelector("i") ?? null;
let sidebarBackdrop = document.querySelector("#sidebar-backdrop");
const content = document.querySelector("#content");
const DASHBOARD_PAGE_LABELS = {
  home: "Sistema",
  perfil: "Perfil",
  clientes: "Clientes",
  pedidos: "Pedidos",
  historial: "Historial",
  inventario: "Inventario",
  ventas: "Ventas",
  configuracion: "Configuraciones",
  developer: "Developer"
};
const mobileSidebarBreakpoint = window.matchMedia("(max-width: 980px)");
const homeDashboardState = {
  intervalId: null,
  chartVentasMes: null,
  chartPedidos: null,
  isFetching: false,
  ingresosPeriodo: "mes"
};
const developerPanelState = {
  payload: null,
  selectedTable: "",
  editPrimaryKey: null
};
const sharedUiPreferencesState = {
  intervalId: null,
  isFetching: false,
  lastSyncedAt: 0
};
const SHARED_UI_SYNC_INTERVAL_MS = 8000;
const TABLE_PAGE_SIZE = 5;
let tablePaginationSequence = 0;

function isMobileSidebarViewport() {
  return Boolean(mobileSidebarBreakpoint?.matches);
}

function syncSidebarState() {
  const isOpen = Boolean(sidebar?.classList.contains("open"));
  const isMobile = isMobileSidebarViewport();

  document.body.classList.toggle("sidebar-expanded", isOpen && !isMobile);
  document.body.classList.toggle("sidebar-mobile-open", isOpen && isMobile);

  if (sidebarBackdrop) {
    sidebarBackdrop.hidden = !(isOpen && isMobile);
  }

  if (mobileNavToggle) {
    mobileNavToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
  }

  if (mobileNavToggleIcon) {
    mobileNavToggleIcon.classList.toggle("bx-menu", !isOpen);
    mobileNavToggleIcon.classList.toggle("bx-x", isOpen);
  }
}

function openSidebar() {
  if (!sidebar) return;
  sidebar.classList.add("open");
  menuBtnChange();
  syncSidebarState();
}

function closeSidebar() {
  if (!sidebar) return;
  sidebar.classList.remove("open");
  menuBtnChange();
  syncSidebarState();
}

function toggleSidebar() {
  if (!sidebar) return;
  if (sidebar.classList.contains("open")) {
    closeSidebar();
    return;
  }

  openSidebar();
}

function getDashboardPageLabel(page) {
  return DASHBOARD_PAGE_LABELS[String(page ?? "").trim()] ?? "Sistema";
}

function setDashboardCurrentPage(page) {
  const currentPage = String(page ?? "").trim();
  const pageLabelNode = document.querySelector("#mobile-current-page");
  if (pageLabelNode) {
    pageLabelNode.textContent = getDashboardPageLabel(currentPage);
  }

  document.querySelectorAll(".nav-list a[data-page]").forEach((link) => {
    link.classList.toggle("is-active", String(link.dataset.page ?? "") === currentPage);
  });
}

function handleSidebarViewportChange() {
  if (!sidebar) return;

  if (isMobileSidebarViewport()) {
    closeSidebar();
    return;
  }

  syncSidebarState();
}

if (closeBtn) {
  closeBtn.addEventListener("click", toggleSidebar);
}

if (mobileNavToggle) {
  mobileNavToggle.addEventListener("click", toggleSidebar);
}

if (searchBtn) {
  searchBtn.addEventListener("click", () => {
    if (!sidebar) return;
    if (isMobileSidebarViewport()) {
      openSidebar();
      return;
    }

    toggleSidebar();
  });
}

if (sidebarBackdrop) {
  sidebarBackdrop.addEventListener("click", closeSidebar);
}

// Function to change the menu button icon
function menuBtnChange() {
  if (!closeBtn || !sidebar) return;

  if (sidebar.classList.contains("open")) {
    closeBtn.classList.replace("bx-menu", "bx-menu-alt-right"); // Change icon to indicate closing
  } else {
    closeBtn.classList.replace("bx-menu-alt-right", "bx-menu"); // Change icon to indicate opening
  }
}

async function loadRouteContent(page) {
  if (!content) return;
  const html = await fetch(`?route=${encodeURIComponent(page)}`).then((r) => r.text());
  content.innerHTML = html;
  setDashboardCurrentPage(page);
  if (isMobileSidebarViewport()) {
    closeSidebar();
  }
  await bootRouteModules(page);
}

async function bootRouteModules(page) {
  const isHomeRoute = page === "home" || Boolean(content?.querySelector('[data-home-dashboard="1"]'));
  if (isHomeRoute) {
    await initHomeDashboardRealtime();
    initTablePagination(content);
    initClientesModule(content);
    initSharedUiPreferencesSync();
    return;
  }

  destroyHomeDashboardRealtime();
  initTablePagination(content);
  initClientesModule(content);
  await initDeveloperPanel();
  initSharedUiPreferencesSync();
}

function normalizeClientesSearchText(value) {
  return String(value ?? "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim();
}

function initClientesModule(scope = content || document) {
  const root = scope instanceof Element || scope instanceof Document ? scope : document;
  const searchInput = root.querySelector("#clientes-search");
  const summaryNode = root.querySelector("#clientes-filter-summary");
  const emptyNode = root.querySelector("#clientes-filter-empty");
  const rows = [...root.querySelectorAll("#clientes-table-body > tr[data-clientes-search]")];

  if (!searchInput || rows.length === 0) {
    if (emptyNode) {
      emptyNode.hidden = true;
    }
    return;
  }

  const totalRows = rows.length;

  const renderFilter = () => {
    const term = normalizeClientesSearchText(searchInput.value);
    let visibleRows = 0;

    rows.forEach((row) => {
      const rowText = normalizeClientesSearchText(row.dataset.clientesSearch ?? "");
      const matches = term === "" || rowText.includes(term);

      row.hidden = !matches;
      if (matches) {
        visibleRows += 1;
      }
    });

    if (summaryNode) {
      summaryNode.textContent = term === ""
        ? `Mostrando ${totalRows} clientes`
        : `${visibleRows} de ${totalRows} clientes coinciden`;
    }

    if (emptyNode) {
      emptyNode.hidden = visibleRows > 0;
    }
  };

  searchInput.addEventListener("input", renderFilter);
  renderFilter();
}

function getTablePaginationId(table) {
  if (!table.dataset.paginationId) {
    tablePaginationSequence += 1;
    table.dataset.paginationId = `table-pagination-${tablePaginationSequence}`;
  }
  return table.dataset.paginationId;
}

function isPlaceholderTableRow(row) {
  if (!row) return false;
  const cells = row.querySelectorAll("td, th");
  if (cells.length !== 1) return false;
  const colspan = Number(cells[0].getAttribute("colspan") ?? 1);
  return Number.isFinite(colspan) && colspan > 1;
}

function initTablePagination(scope = content || document) {
  const root = scope instanceof Element || scope instanceof Document ? scope : document;
  const tables = [...root.querySelectorAll("table")];

  tables.forEach((table) => {
    const tbody = table.tBodies?.[0];
    if (!tbody) return;

    const rows = [...tbody.querySelectorAll(":scope > tr")];
    if (rows.length === 0) return;

    const tableId = getTablePaginationId(table);
    const existingControls = root.querySelector(`.table-pagination[data-pagination-for="${tableId}"]`);
    if (existingControls) {
      existingControls.remove();
    }

    rows.forEach((row) => {
      row.hidden = false;
    });

    const pageSizeRaw = Number(table.dataset.pageSize ?? TABLE_PAGE_SIZE);
    const pageSize = Number.isFinite(pageSizeRaw) && pageSizeRaw > 0
      ? Math.floor(pageSizeRaw)
      : TABLE_PAGE_SIZE;

    const hasPlaceholder = rows.length === 1 && isPlaceholderTableRow(rows[0]);
    if (hasPlaceholder || rows.length <= pageSize) {
      table.dataset.paginationCurrentPage = "1";
      return;
    }

    const totalRows = rows.length;
    const totalPages = Math.ceil(totalRows / pageSize);
    let currentPage = Number(table.dataset.paginationCurrentPage ?? 1);
    if (!Number.isFinite(currentPage) || currentPage < 1) {
      currentPage = 1;
    }

    const pagination = document.createElement("div");
    pagination.className = "table-pagination";
    pagination.dataset.paginationFor = tableId;

    const nav = document.createElement("div");
    nav.className = "table-pagination-nav";

    const prevBtn = document.createElement("button");
    prevBtn.type = "button";
    prevBtn.className = "table-pagination-btn";
    prevBtn.setAttribute("aria-label", "Página anterior");
    prevBtn.textContent = "←";

    const pagesNode = document.createElement("div");
    pagesNode.className = "table-pagination-pages";

    const nextBtn = document.createElement("button");
    nextBtn.type = "button";
    nextBtn.className = "table-pagination-btn";
    nextBtn.setAttribute("aria-label", "Página siguiente");
    nextBtn.textContent = "→";

    const info = document.createElement("p");
    info.className = "table-pagination-info";

    nav.append(prevBtn, pagesNode, nextBtn);
    pagination.append(nav, info);

    const tableParent = table.parentElement;
    if (tableParent) {
      if (table.nextSibling) {
        tableParent.insertBefore(pagination, table.nextSibling);
      } else {
        tableParent.appendChild(pagination);
      }
    }

    const renderPage = (targetPage) => {
      currentPage = Math.min(Math.max(targetPage, 1), totalPages);
      table.dataset.paginationCurrentPage = String(currentPage);

      const start = (currentPage - 1) * pageSize;
      const end = start + pageSize;

      rows.forEach((row, index) => {
        row.hidden = !(index >= start && index < end);
      });

      prevBtn.disabled = currentPage <= 1;
      nextBtn.disabled = currentPage >= totalPages;

      pagesNode.innerHTML = "";
      for (let pageNumber = 1; pageNumber <= totalPages; pageNumber += 1) {
        const pageBtn = document.createElement("button");
        pageBtn.type = "button";
        pageBtn.className = "table-pagination-btn";
        pageBtn.textContent = String(pageNumber);
        if (pageNumber === currentPage) {
          pageBtn.classList.add("is-active");
          pageBtn.setAttribute("aria-current", "page");
        }
        pageBtn.addEventListener("click", () => {
          renderPage(pageNumber);
        });
        pagesNode.appendChild(pageBtn);
      }

      const shownFrom = start + 1;
      const shownTo = Math.min(end, totalRows);
      info.textContent = `Mostrando ${shownFrom}-${shownTo} de ${totalRows} registros`;
    };

    prevBtn.addEventListener("click", () => {
      renderPage(currentPage - 1);
    });

    nextBtn.addEventListener("click", () => {
      renderPage(currentPage + 1);
    });

    renderPage(currentPage);
  });
}

function destroyHomeDashboardRealtime() {
  if (homeDashboardState.intervalId) {
    window.clearInterval(homeDashboardState.intervalId);
    homeDashboardState.intervalId = null;
  }

  if (homeDashboardState.chartVentasMes) {
    homeDashboardState.chartVentasMes.destroy();
    homeDashboardState.chartVentasMes = null;
  }

  if (homeDashboardState.chartPedidos) {
    homeDashboardState.chartPedidos.destroy();
    homeDashboardState.chartPedidos = null;
  }

  homeDashboardState.isFetching = false;
}

function getHomeDashboardInitialData() {
  const node = document.querySelector("#home-dashboard-data");
  if (!node) return null;

  try {
    return JSON.parse(node.textContent || "{}");
  } catch (error) {
    return null;
  }
}

function getDeveloperPanelInitialData() {
  const node = document.querySelector("#developer-panel-data");
  if (!node) return null;

  try {
    return JSON.parse(node.textContent || "{}");
  } catch (error) {
    return null;
  }
}

function encodeDeveloperPayload(value) {
  return encodeURIComponent(JSON.stringify(value ?? {}));
}

function decodeDeveloperPayload(value) {
  try {
    return JSON.parse(decodeURIComponent(String(value ?? "")));
  } catch (error) {
    return null;
  }
}

function setDeveloperFeedback(message, type = "error") {
  const feedback = document.querySelector("#developer-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setNodeTextValue(selector, value) {
  const node = document.querySelector(selector);
  if (!node) return;
  node.textContent = String(value ?? "-");
}

function setDeveloperEditFeedback(message, type = "error") {
  const feedback = document.querySelector("#developer-row-edit-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function updateDeveloperDbStatus(statusValue) {
  const node = document.querySelector("#developer-db-status");
  if (!node) return;

  const status = String(statusValue ?? "").toLowerCase().trim();
  const isOk = status === "ok";

  node.classList.remove("ok", "error");
  node.classList.add(isOk ? "ok" : "error");
  node.textContent = isOk ? "Conectada" : "Error";
}

function normalizeDeveloperPayload(data) {
  return data && typeof data === "object" ? data : {};
}

function getDeveloperCurrentTableManager() {
  const payload = normalizeDeveloperPayload(developerPanelState.payload);
  const tableManager = payload.tableManager && typeof payload.tableManager === "object"
    ? payload.tableManager
    : {};

  return tableManager;
}

function getDeveloperSelectedTable() {
  const tableManager = getDeveloperCurrentTableManager();
  const selected = String(tableManager.selectedTable ?? developerPanelState.selectedTable ?? "").trim();
  return selected;
}

function getDeveloperSelectedTableInfo(tables, tableName) {
  const catalog = Array.isArray(tables) ? tables : [];
  return catalog.find((item) => String(item.table ?? "") === String(tableName ?? "").trim()) || null;
}

function formatDeveloperValue(value) {
  if (value === null || value === undefined) {
    return '<span class="developer-null">NULL</span>';
  }

  if (value === "") {
    return '<span class="developer-empty-string">(vacio)</span>';
  }

  return escapeHtml(value);
}

function renderDeveloperTableCatalog(tables, selectedTable) {
  const node = document.querySelector("#developer-table-catalog");
  if (!node) return;

  const rows = Array.isArray(tables) ? tables : [];
  if (rows.length === 0) {
    node.innerHTML = '<p class="developer-empty-state">No hay tablas disponibles.</p>';
    return;
  }

  node.innerHTML = rows
    .map((item) => {
      const tableName = String(item.table ?? "").trim();
      const isActive = tableName === selectedTable;
      const rowCount = Number(item.rowCount ?? 0);
      const columnCount = Number(item.columnCount ?? 0);

      return `
        <button
          type="button"
          class="developer-table-chip ${isActive ? "is-active" : ""}"
          data-action="developer-select-table"
          data-table="${escapeHtml(tableName)}"
        >
          <strong>${escapeHtml(tableName)}</strong>
          <span>${escapeHtml(rowCount)} registros</span>
          <small>${escapeHtml(columnCount)} columnas</small>
        </button>
      `;
    })
    .join("");
}

function renderDeveloperTableSelector(tables, selectedTable) {
  const select = document.querySelector("#developer-table-select");
  if (!select) return;

  const rows = Array.isArray(tables) ? tables : [];
  select.innerHTML = rows
    .map((item) => {
      const tableName = String(item.table ?? "").trim();
      const isSelected = tableName === selectedTable;
      return `
        <option value="${escapeHtml(tableName)}" ${isSelected ? "selected" : ""}>
          ${escapeHtml(tableName)}
        </option>
      `;
    })
    .join("");

  select.value = selectedTable;
}

function renderDeveloperTableSummary(tableManager, tableInfo) {
  const columns = Array.isArray(tableManager.columns) ? tableManager.columns : [];
  const primaryKey = Array.isArray(tableManager.primaryKey) ? tableManager.primaryKey : [];

  setNodeTextValue("#developer-table-row-count", Number(tableInfo?.rowCount ?? 0));
  setNodeTextValue("#developer-table-column-count", columns.length);
  setNodeTextValue("#developer-table-primary-key", primaryKey.length > 0 ? primaryKey.join(", ") : "-");
  setNodeTextValue("#developer-table-limit", Number(tableManager.limit ?? 25));

  const clearBtn = document.querySelector('[data-action="developer-clear-table"]');
  if (clearBtn) {
    const canClear = Boolean(tableInfo?.supportsBulkClear);
    clearBtn.disabled = !canClear || !String(tableManager.selectedTable ?? "").trim();
    clearBtn.title = canClear
      ? "Eliminar todos los registros visibles de esta tabla"
      : "Esta tabla no puede vaciarse completa desde el panel";
  }
}

function renderDeveloperTableRows(tableManager) {
  const head = document.querySelector("#developer-records-head");
  const body = document.querySelector("#developer-records-body");
  if (!head || !body) return;

  const columns = Array.isArray(tableManager.columns) ? tableManager.columns : [];
  const rows = Array.isArray(tableManager.rows) ? tableManager.rows : [];
  const primaryKey = Array.isArray(tableManager.primaryKey) ? tableManager.primaryKey : [];
  const editableColumns = columns.filter((column) => Boolean(column.is_editable));
  const canMutateRows = primaryKey.length > 0;

  if (columns.length === 0) {
    head.innerHTML = "";
    body.innerHTML = `
      <tr>
        <td colspan="2" style="text-align:center;">La tabla seleccionada no tiene columnas disponibles.</td>
      </tr>
    `;
    return;
  }

  head.innerHTML = `
    <tr>
      ${columns.map((column) => `<th>${escapeHtml(column.name ?? "-")}</th>`).join("")}
      <th>Acciones</th>
    </tr>
  `;

  if (rows.length === 0) {
    body.innerHTML = `
      <tr>
        <td colspan="${columns.length + 1}" style="text-align:center;">No hay registros para mostrar.</td>
      </tr>
    `;
    return;
  }

  body.innerHTML = rows
    .map((row) => {
      const primaryPayload = {};
      primaryKey.forEach((columnName) => {
        primaryPayload[columnName] = row?.[columnName] ?? null;
      });
      const encodedPrimaryKey = encodeDeveloperPayload(primaryPayload);

      return `
        <tr>
          ${columns.map((column) => {
            const value = row?.[column.name ?? ""];
            const extraClass = value === null || value === undefined ? " developer-cell-null" : "";
            const columnName = String(column.name ?? "-");
            return `<td class="developer-record-cell${extraClass}" data-label="${escapeHtml(columnName)}">${formatDeveloperValue(value)}</td>`;
          }).join("")}
          <td class="developer-record-actions" data-label="Acciones">
            <button
              class="btn dev-btn secondary developer-row-btn"
              type="button"
              data-action="developer-edit-row"
              data-primary-key="${encodedPrimaryKey}"
              ${canMutateRows && editableColumns.length > 0 ? "" : "disabled"}
            >
              Editar
            </button>
            <button
              class="btn dev-btn danger subtle developer-row-btn"
              type="button"
              data-action="developer-delete-row"
              data-primary-key="${encodedPrimaryKey}"
              ${canMutateRows ? "" : "disabled"}
            >
              Eliminar
            </button>
          </td>
        </tr>
      `;
    })
    .join("");
}

function renderDeveloperUiPreferences(preferences) {
  const uiPreferences = preferences && typeof preferences === "object" ? preferences : {};
  const showVentasActionsColumn = uiPreferences.ventasShowActionsColumn !== false;
  const showHistorialActionsColumn = uiPreferences.historialShowActionsColumn !== false;
  const syncToggle = (config) => {
    const stateNode = document.querySelector(config.stateSelector);
    const copyNode = document.querySelector(config.copySelector);
    const toggleBtn = document.querySelector(config.buttonSelector);

    if (stateNode) {
      stateNode.textContent = config.enabled ? "Visible" : "Oculta";
    }

    if (copyNode) {
      copyNode.textContent = config.enabled
        ? config.visibleCopy
        : config.hiddenCopy;
    }

    if (!toggleBtn) return;

    toggleBtn.dataset.enabled = config.enabled ? "1" : "0";
    toggleBtn.textContent = config.enabled ? "Ocultar columna" : "Mostrar columna";
    toggleBtn.classList.remove("primary", "secondary", "danger", "subtle");

    if (config.enabled) {
      toggleBtn.classList.add("danger", "subtle");
      return;
    }

    toggleBtn.classList.add("primary");
  };

  syncToggle({
    enabled: showVentasActionsColumn,
    stateSelector: "#developer-ui-ventas-actions-state",
    copySelector: "#developer-ui-ventas-actions-copy",
    buttonSelector: '[data-action="developer-toggle-ventas-actions-column"]',
    visibleCopy: "La columna de acciones se muestra en el listado principal de ventas para todos los roles.",
    hiddenCopy: "La columna de acciones está oculta globalmente; la gestión sigue disponible al hacer clic en la fila."
  });

  syncToggle({
    enabled: showHistorialActionsColumn,
    stateSelector: "#developer-ui-historial-actions-state",
    copySelector: "#developer-ui-historial-actions-copy",
    buttonSelector: '[data-action="developer-toggle-historial-actions-column"]',
    visibleCopy: "La columna de acciones se muestra en el listado principal del historial para todos los roles.",
    hiddenCopy: "La columna de acciones está oculta globalmente; la gestión sigue disponible al hacer clic en la fila."
  });
}

function getSharedUiPreferencesTargets(scope = content || document) {
  const root = scope instanceof Element || scope instanceof Document ? scope : document;
  return {
    root,
    ventasPanel: root.querySelector('[data-shared-ui-scope="ventas"]'),
    historialPanel: root.querySelector('[data-shared-ui-scope="historial"]'),
    developerPanel: root.querySelector('[data-developer-panel="1"]')
  };
}

function hasSharedUiPreferencesTargets(scope = content || document) {
  const targets = getSharedUiPreferencesTargets(scope);
  return Boolean(targets.ventasPanel || targets.historialPanel || targets.developerPanel);
}

function applySharedUiPreferences(preferences, scope = content || document) {
  const uiPreferences = preferences && typeof preferences === "object" ? preferences : {};
  const targets = getSharedUiPreferencesTargets(scope);
  const showVentasActionsColumn = uiPreferences.ventasShowActionsColumn !== false;
  const showHistorialActionsColumn = uiPreferences.historialShowActionsColumn !== false;

  if (targets.ventasPanel) {
    targets.ventasPanel.classList.toggle("ventas-actions-hidden", !showVentasActionsColumn);
  }

  if (targets.historialPanel) {
    targets.historialPanel.classList.toggle("historial-actions-hidden", !showHistorialActionsColumn);
  }

  if (targets.developerPanel) {
    renderDeveloperUiPreferences(uiPreferences);
  }
}

async function requestDashboardUiData() {
  const response = await fetch("?route=dashboard-ui-data");
  const result = await response.json();

  if (!response.ok || !result?.ok) {
    throw new Error(result?.message ?? "No se pudo sincronizar la UI compartida.");
  }

  return result.data && typeof result.data === "object" ? result.data : {};
}

async function syncSharedUiPreferences(force = false) {
  if (!hasSharedUiPreferencesTargets()) {
    stopSharedUiPreferencesSync();
    return;
  }

  if (sharedUiPreferencesState.isFetching) {
    return;
  }

  const now = Date.now();
  if (!force && sharedUiPreferencesState.lastSyncedAt > 0) {
    const elapsed = now - sharedUiPreferencesState.lastSyncedAt;
    if (elapsed < 1500) {
      return;
    }
  }

  sharedUiPreferencesState.isFetching = true;
  try {
    const preferences = await requestDashboardUiData();
    applySharedUiPreferences(preferences);
    sharedUiPreferencesState.lastSyncedAt = Date.now();
  } catch (error) {
    console.error(error);
  } finally {
    sharedUiPreferencesState.isFetching = false;
  }
}

function stopSharedUiPreferencesSync() {
  if (sharedUiPreferencesState.intervalId) {
    window.clearInterval(sharedUiPreferencesState.intervalId);
    sharedUiPreferencesState.intervalId = null;
  }
}

function initSharedUiPreferencesSync() {
  stopSharedUiPreferencesSync();

  if (!hasSharedUiPreferencesTargets()) {
    return;
  }

  syncSharedUiPreferences(true);

  sharedUiPreferencesState.intervalId = window.setInterval(() => {
    if (!hasSharedUiPreferencesTargets()) {
      stopSharedUiPreferencesSync();
      return;
    }

    syncSharedUiPreferences();
  }, SHARED_UI_SYNC_INTERVAL_MS);
}

function renderDeveloperPrimarySummary(primaryKey) {
  const node = document.querySelector("#developer-row-edit-primary");
  if (!node) return;

  const payload = primaryKey && typeof primaryKey === "object" ? primaryKey : {};
  const entries = Object.entries(payload);
  if (entries.length === 0) {
    node.innerHTML = '<span class="developer-empty-state">Sin clave primaria</span>';
    return;
  }

  node.innerHTML = entries
    .map(([key, value]) => `
      <span class="developer-primary-badge">
        ${escapeHtml(key)} = ${escapeHtml(value ?? "NULL")}
      </span>
    `)
    .join("");
}

function getDeveloperInputType(column) {
  const dataType = String(column?.data_type ?? "").toLowerCase().trim();
  if (["tinyint", "smallint", "mediumint", "int", "bigint", "decimal", "float", "double"].includes(dataType)) {
    return "number";
  }
  if (dataType === "date") {
    return "date";
  }
  if (["datetime", "timestamp"].includes(dataType)) {
    return "datetime-local";
  }
  if (dataType === "time") {
    return "time";
  }
  return "text";
}

function getDeveloperInputValue(column, value) {
  if (value === null || value === undefined) {
    return "";
  }

  const inputType = getDeveloperInputType(column);
  const rawValue = String(value);
  if (inputType === "datetime-local") {
    return rawValue.replace(" ", "T").slice(0, 19);
  }

  return rawValue;
}

function normalizeDeveloperFieldOutput(column, value) {
  const inputType = getDeveloperInputType(column);
  if (inputType === "datetime-local") {
    return value ? String(value).replace("T", " ") : "";
  }
  return value;
}

function renderDeveloperEditFields(row) {
  const node = document.querySelector("#developer-row-edit-fields");
  if (!node) return;

  const tableManager = getDeveloperCurrentTableManager();
  const columns = Array.isArray(tableManager.columns) ? tableManager.columns : [];
  const editableColumns = columns.filter((column) => Boolean(column.is_editable));

  if (editableColumns.length === 0) {
    node.innerHTML = '<p class="developer-empty-state">Esta tabla no tiene columnas editables desde el panel.</p>';
    return;
  }

  node.innerHTML = editableColumns
    .map((column) => {
      const columnName = String(column.name ?? "").trim();
      const value = getDeveloperInputValue(column, row?.[columnName]);
      const inputType = getDeveloperInputType(column);
      const defaultValue = column.default === null || column.default === undefined
        ? ""
        : String(column.default);
      const nullableText = column.is_nullable ? "Permite NULL" : "Obligatorio";
      const hint = `${column.column_type || inputType} · ${nullableText}`;

      if (["text", "mediumtext", "longtext", "tinytext"].includes(String(column.data_type ?? "").toLowerCase().trim())) {
        return `
          <div class="developer-form-field">
            <label for="developer-field-${escapeHtml(columnName)}">
              ${escapeHtml(columnName)}
              <small>${escapeHtml(hint)}</small>
            </label>
            <textarea
              id="developer-field-${escapeHtml(columnName)}"
              name="${escapeHtml(columnName)}"
              rows="4"
              placeholder="${column.is_nullable ? "Deja vacio para NULL" : `Default: ${escapeHtml(defaultValue || "-")}`}"
            >${escapeHtml(value)}</textarea>
          </div>
        `;
      }

      const step = inputType === "number" ? "step=\"any\"" : "";
      return `
        <div class="developer-form-field">
          <label for="developer-field-${escapeHtml(columnName)}">
            ${escapeHtml(columnName)}
            <small>${escapeHtml(hint)}</small>
          </label>
          <input
            id="developer-field-${escapeHtml(columnName)}"
            name="${escapeHtml(columnName)}"
            type="${escapeHtml(inputType)}"
            value="${escapeHtml(value)}"
            ${step}
            placeholder="${column.is_nullable ? "Deja vacio para NULL" : `Default: ${escapeHtml(defaultValue || "-")}`}"
          >
        </div>
      `;
    })
    .join("");
}

function openDeveloperEditModal(primaryKey) {
  const tableManager = getDeveloperCurrentTableManager();
  const rows = Array.isArray(tableManager.rows) ? tableManager.rows : [];
  const primaryColumns = Array.isArray(tableManager.primaryKey) ? tableManager.primaryKey : [];
  const currentPrimaryKey = primaryKey && typeof primaryKey === "object" ? primaryKey : {};

  const row = rows.find((item) => primaryColumns.every((columnName) => String(item?.[columnName] ?? "") === String(currentPrimaryKey?.[columnName] ?? "")));
  if (!row) {
    setDeveloperFeedback("No se encontro el registro seleccionado.", "error");
    return;
  }

  developerPanelState.editPrimaryKey = currentPrimaryKey;
  renderDeveloperPrimarySummary(currentPrimaryKey);
  renderDeveloperEditFields(row);
  setDeveloperEditFeedback("", "error");

  const title = document.querySelector("#developer-row-edit-title");
  if (title) {
    title.textContent = `Editar registro - ${getDeveloperSelectedTable() || "tabla"}`;
  }

  const modal = document.querySelector("#developer-row-edit-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeDeveloperEditModal() {
  developerPanelState.editPrimaryKey = null;
  setDeveloperEditFeedback("", "error");
  const modal = document.querySelector("#developer-row-edit-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function getDeveloperEditPayload() {
  const tableManager = getDeveloperCurrentTableManager();
  const columns = Array.isArray(tableManager.columns) ? tableManager.columns : [];
  const editableColumns = columns.filter((column) => Boolean(column.is_editable));
  const payload = {};

  editableColumns.forEach((column) => {
    const columnName = String(column.name ?? "").trim();
    const field = document.getElementById(`developer-field-${columnName}`);
    if (!field) return;
    payload[columnName] = normalizeDeveloperFieldOutput(column, field.value);
  });

  return payload;
}

function renderDeveloperPanel(data) {
  const payload = normalizeDeveloperPayload(data);
  const info = payload.info && typeof payload.info === "object" ? payload.info : {};
  const resumen = payload.resumen && typeof payload.resumen === "object" ? payload.resumen : {};
  const integridad = payload.integridad && typeof payload.integridad === "object" ? payload.integridad : {};
  const preferences = payload.preferences && typeof payload.preferences === "object" ? payload.preferences : {};
  const usuariosCredenciales = Array.isArray(payload.usuariosCredenciales)
    ? payload.usuariosCredenciales
    : [];
  const tables = Array.isArray(payload.tables) ? payload.tables : [];
  const tableManager = payload.tableManager && typeof payload.tableManager === "object"
    ? payload.tableManager
    : {};

  developerPanelState.payload = payload;
  developerPanelState.selectedTable = String(tableManager.selectedTable ?? "").trim();

  setNodeTextValue("#developer-info-app", info.appName ?? "RECALDE");
  setNodeTextValue("#developer-info-version", info.appVersion ?? "1.0.0");
  setNodeTextValue("#developer-info-php", info.phpVersion ?? "-");
  setNodeTextValue("#developer-info-role", payload.rolActual ?? "-");
  setNodeTextValue("#developer-info-generated", info.generatedAt ?? "-");
  updateDeveloperDbStatus(info.dbStatus ?? "error");

  setNodeTextValue("#developer-count-usuarios", Number(resumen.usuarios ?? 0));
  setNodeTextValue("#developer-count-clientes", Number(resumen.clientes ?? 0));
  setNodeTextValue("#developer-count-productos", Number(resumen.productos ?? 0));
  setNodeTextValue("#developer-count-pedidos", Number(resumen.pedidos ?? 0));
  setNodeTextValue("#developer-count-ventas", Number(resumen.ventas ?? 0));
  setNodeTextValue("#developer-count-historial", Number(resumen.historial ?? 0));

  setNodeTextValue("#developer-check-pedidos-sin-detalle", Number(integridad.pedidosSinDetalle ?? 0));
  setNodeTextValue("#developer-check-pedidos-descuadrados", Number(integridad.pedidosTotalesDescuadrados ?? 0));
  setNodeTextValue("#developer-check-ventas-sin-historial", Number(integridad.ventasSinHistorial ?? 0));

  renderDeveloperTableSelector(tables, developerPanelState.selectedTable);
  renderDeveloperTableCatalog(tables, developerPanelState.selectedTable);
  renderDeveloperTableSummary(tableManager, getDeveloperSelectedTableInfo(tables, developerPanelState.selectedTable));
  renderDeveloperTableRows(tableManager);
  renderDeveloperUiPreferences(preferences);
  applySharedUiPreferences(preferences);
  renderDeveloperUsersCredentials(usuariosCredenciales);
  initTablePagination(content);
}

function renderDeveloperUsersCredentials(users) {
  const tbody = document.querySelector("#developer-users-body");
  if (!tbody) return;

  const rows = Array.isArray(users) ? users : [];
  if (rows.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="8" style="text-align:center;">No hay usuarios para mostrar.</td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = rows
    .map((item) => {
      const passwordStored = String(item.contrasena ?? "");
      const hashLabel = Boolean(item.password_is_hash)
        ? '<small class="developer-hash-label">hash</small>'
        : "";
      const mustChange = Number(item.debe_cambiar_contrasena ?? 0) === 1;
      const userId = Number(item.id ?? 0);
      const username = String(item.usuario ?? "");

      return `
        <tr>
          <td data-label="ID">#${escapeHtml(item.id ?? 0)}</td>
          <td data-label="Usuario">${escapeHtml(item.usuario ?? "-")}</td>
          <td data-label="Correo">${escapeHtml(item.correo ?? "-")}</td>
          <td data-label="Rol">${escapeHtml(item.nombre_rol ?? "-")}</td>
          <td data-label="Estado">${escapeHtml(item.estado ?? "-")}</td>
          <td data-label="Cambio forzado">${mustChange ? "Si" : "No"}</td>
          <td class="mono" data-label="Contraseña almacenada">${escapeHtml(passwordStored)} ${hashLabel}</td>
          <td data-label="Acciones">
            <button
              class="btn dev-btn secondary developer-reset-btn"
              type="button"
              data-action="developer-reset-password-user"
              data-user-id="${escapeHtml(userId)}"
              data-username="${escapeHtml(username)}"
            >
              Resetear contraseña
            </button>
          </td>
        </tr>
      `;
    })
    .join("");
}

async function requestDeveloperData(tableName = "") {
  const params = new URLSearchParams();
  params.set("route", "developer-data");
  if (String(tableName ?? "").trim()) {
    params.set("table", String(tableName).trim());
  }

  const response = await fetch(`?${params.toString()}`);
  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La respuesta del módulo Developer no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo cargar el panel de desarrollador.");
  }

  return data.data || {};
}

async function requestDeveloperAction(action, payload = {}) {
  const params = new URLSearchParams();
  params.append("action", String(action ?? ""));
  if (payload && typeof payload === "object") {
    Object.entries(payload).forEach(([key, value]) => {
      if (value === undefined || value === null) return;
      params.append(String(key), String(value));
    });
  }

  const response = await fetch("?route=developer-action", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La respuesta del módulo Developer no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo ejecutar la acción de desarrollador.");
  }

  return data;
}

async function initDeveloperPanel() {
  if (!content?.querySelector('[data-developer-panel="1"]')) {
    return;
  }

  setDeveloperFeedback("", "error");
  closeDeveloperEditModal();

  const initialData = getDeveloperPanelInitialData();
  if (initialData) {
    renderDeveloperPanel(initialData);
    return;
  }

  try {
    const data = await requestDeveloperData();
    renderDeveloperPanel(data);
  } catch (error) {
    setDeveloperFeedback(error.message, "error");
  }
}

function normalizeHomeIngresosPeriodo(value) {
  const periodo = String(value ?? "").toLowerCase().trim();
  return ["dia", "semana", "mes"].includes(periodo) ? periodo : "mes";
}

function getHomeIngresosTitle(periodo) {
  if (periodo === "dia") return "Ingresos por Día";
  if (periodo === "semana") return "Ingresos por Semana";
  return "Ingresos por Mes";
}

function getHomeIngresosSubtitle(periodo) {
  if (periodo === "dia") return "Detalle diario de ingresos registrados.";
  if (periodo === "semana") return "Comparativa semanal de ingresos.";
  return "Tendencia mensual del rendimiento comercial acumulado.";
}

function getHomeIngresosDatasetLabel(periodo) {
  if (periodo === "dia") return "Ingresos diarios";
  if (periodo === "semana") return "Ingresos semanales";
  return "Ingresos mensuales";
}

function syncHomeIngresosSelector(periodo) {
  const selector = document.querySelector("#home-ingresos-periodo");
  if (selector) {
    const normalized = normalizeHomeIngresosPeriodo(periodo);
    if (selector.value !== normalized) {
      selector.value = normalized;
    }
  }
}

function renderHomeIngresosHead(periodo) {
  const titleNode = document.querySelector("#home-ingresos-title");
  const subtitleNode = document.querySelector("#home-ingresos-subtitle");
  if (titleNode) {
    titleNode.textContent = getHomeIngresosTitle(periodo);
  }
  if (subtitleNode) {
    subtitleNode.textContent = getHomeIngresosSubtitle(periodo);
  }
  syncHomeIngresosSelector(periodo);
}

function renderHomeTopProductos(topProductos) {
  const tbody = document.querySelector("#home-top-productos-body");
  if (!tbody) return;

  const rows = Array.isArray(topProductos) ? topProductos : [];
  if (rows.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="2" style="text-align:center;">Sin datos disponibles.</td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = rows
    .map((item) => `
      <tr>
        <td data-label="Producto">${escapeHtml(item.nombre_producto ?? "-")}</td>
        <td data-label="Cantidad">${escapeHtml(item.total_vendido ?? 0)}</td>
      </tr>
    `)
    .join("");
}

function renderHomeUltimasVentas(ultimasVentas) {
  const tbody = document.querySelector("#home-ultimas-ventas-body");
  if (!tbody) return;

  const rows = Array.isArray(ultimasVentas) ? ultimasVentas : [];
  if (rows.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align:center;">Sin ventas registradas.</td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = rows
    .map((item) => {
      const total = Number(item.total ?? 0);
      const abonado = Number(item.total_abonado ?? total);
      const saldo = Number(item.saldo_pendiente ?? Math.max(total - abonado, 0));
      const estadoPago = normalizeEstadoValue(item.estado_pago ?? (saldo > 0 ? (abonado > 0 ? "parcial" : "pendiente") : "pagado"));
      const badgeClass = getPaymentStatusClass(estadoPago);

      return `
        <tr>
          <td data-label="ID Venta">${escapeHtml(item.id ?? 0)}</td>
          <td data-label="Cliente">${escapeHtml(`${item.nombre ?? ""} ${item.apellido ?? ""}`.trim() || "-")}</td>
          <td data-label="Total">${escapeHtml(formatMoney(total))}</td>
          <td data-label="Abonado">${escapeHtml(formatMoney(abonado))}</td>
          <td data-label="Saldo">${escapeHtml(formatMoney(saldo))}</td>
          <td data-label="Estado Pago"><span class="home-payment-status ${escapeHtml(badgeClass)}">${escapeHtml(capitalize(estadoPago || "pendiente"))}</span></td>
          <td data-label="Fecha">${escapeHtml(item.fecha_venta ?? "-")}</td>
        </tr>
      `;
    })
    .join("");
}

function renderHomeClientesConDeuda(clientesConDeuda) {
  const tbody = document.querySelector("#home-clientes-deuda-body");
  if (!tbody) return;

  const rows = Array.isArray(clientesConDeuda) ? clientesConDeuda : [];
  if (rows.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4" style="text-align:center;">No hay clientes con deuda.</td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = rows
    .map((item) => `
      <tr>
        <td data-label="Cliente">${escapeHtml(`${item.nombre ?? ""} ${item.apellido ?? ""}`.trim() || "-")}</td>
        <td data-label="Cédula">${escapeHtml(item.cedula ?? "-")}</td>
        <td data-label="Ventas">${escapeHtml(item.total_ventas_con_deuda ?? 0)}</td>
        <td data-label="Deuda">${escapeHtml(formatMoney(item.deuda_total ?? 0))}</td>
      </tr>
    `)
    .join("");
}

function renderHomeUltimosHistorial(ultimosHistorial) {
  const tbody = document.querySelector("#home-ultimos-historial-body");
  if (!tbody) return;

  const rows = Array.isArray(ultimosHistorial) ? ultimosHistorial : [];
  if (rows.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align:center;">Sin registros de historial.</td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = rows
    .map((item) => {
      const estadoPago = normalizeEstadoValue(item.estado_pago ?? "pendiente");
      const badgeClass = getPaymentStatusClass(estadoPago);

      return `
        <tr>
          <td data-label="Historial">#${escapeHtml(item.id ?? 0)}</td>
          <td data-label="Venta">#${escapeHtml(item.id_venta ?? 0)}</td>
          <td data-label="Cliente">${escapeHtml(`${item.nombre ?? ""} ${item.apellido ?? ""}`.trim() || "-")}</td>
          <td data-label="Total">${escapeHtml(formatMoney(item.total ?? 0))}</td>
          <td data-label="Abonado">${escapeHtml(formatMoney(item.total_abonado ?? 0))}</td>
          <td data-label="Saldo">${escapeHtml(formatMoney(item.saldo_pendiente ?? 0))}</td>
          <td data-label="Estado"><span class="home-payment-status ${escapeHtml(badgeClass)}">${escapeHtml(capitalize(estadoPago || "pendiente"))}</span></td>
        </tr>
      `;
    })
    .join("");
}

function renderHomeCarteraSummary(carteraResumen) {
  const cartera = carteraResumen && typeof carteraResumen === "object" ? carteraResumen : {};

  setNodeText("#home-cartera-total-abonado", formatMoney(cartera.total_abonado ?? 0));
  setNodeText("#home-cartera-total-pendiente", formatMoney(cartera.total_pendiente ?? 0));
  setNodeText("#home-cartera-ventas-con-saldo", Number(cartera.ventas_con_saldo ?? 0));
}

function renderHomeHistorialSummary(historialResumen) {
  const resumen = historialResumen && typeof historialResumen === "object" ? historialResumen : {};
  setNodeText("#home-historial-vigentes", Number(resumen.total_vigentes ?? 0));
  setNodeText("#home-historial-anulados", Number(resumen.total_anulados ?? 0));
}

function renderHomeCharts(data) {
  if (typeof Chart === "undefined") {
    return;
  }

  const ventasCanvas = document.querySelector("#chartVentasMes");
  const pedidosCanvas = document.querySelector("#chartPedidos");
  if (!ventasCanvas || !pedidosCanvas) return;

  const periodoIngresos = normalizeHomeIngresosPeriodo(data.periodoIngresos ?? homeDashboardState.ingresosPeriodo);
  const labelsIngresos = Array.isArray(data.labelsIngresos)
    ? data.labelsIngresos
    : (Array.isArray(data.labelsMes) ? data.labelsMes : []);
  const datosIngresos = Array.isArray(data.datosIngresos)
    ? data.datosIngresos
    : (Array.isArray(data.datosVentasMes) ? data.datosVentasMes : []);
  const ingresosDatasetLabel = getHomeIngresosDatasetLabel(periodoIngresos);

  const pedidosEstadosObj = data.pedidosEstados && typeof data.pedidosEstados === "object"
    ? data.pedidosEstados
    : {};
  const pedidosLabels = Object.keys(pedidosEstadosObj);
  const pedidosData = Object.values(pedidosEstadosObj);

  if (!homeDashboardState.chartVentasMes) {
    homeDashboardState.chartVentasMes = new Chart(ventasCanvas, {
      type: "line",
      data: {
        labels: labelsIngresos,
        datasets: [{
          label: ingresosDatasetLabel,
          data: datosIngresos,
          borderColor: "#00c3ff",
          backgroundColor: "rgba(0, 195, 255, 0.15)",
          borderWidth: 3,
          tension: 0.32,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: "#e8f7ff" }
          }
        },
        scales: {
          x: {
            ticks: { color: "#b8dff6" },
            grid: { color: "rgba(184, 223, 246, 0.12)" }
          },
          y: {
            ticks: { color: "#b8dff6" },
            grid: { color: "rgba(184, 223, 246, 0.12)" }
          }
        }
      }
    });
  } else {
    homeDashboardState.chartVentasMes.data.labels = labelsIngresos;
    homeDashboardState.chartVentasMes.data.datasets[0].label = ingresosDatasetLabel;
    homeDashboardState.chartVentasMes.data.datasets[0].data = datosIngresos;
    homeDashboardState.chartVentasMes.update("none");
  }

  if (!homeDashboardState.chartPedidos) {
    homeDashboardState.chartPedidos = new Chart(pedidosCanvas, {
      type: "doughnut",
      data: {
        labels: pedidosLabels,
        datasets: [{
          label: "Pedidos por estado",
          data: pedidosData,
          backgroundColor: [
            "#00c3ff",
            "#2effc0",
            "#ffc107",
            "#7aff7a",
            "#ff5e5e"
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: "#e8f7ff" }
          }
        }
      }
    });
  } else {
    homeDashboardState.chartPedidos.data.labels = pedidosLabels;
    homeDashboardState.chartPedidos.data.datasets[0].data = pedidosData;
    homeDashboardState.chartPedidos.update("none");
  }
}

function renderHomePedidosEstadoSummary(pedidosEstados) {
  const estados = pedidosEstados && typeof pedidosEstados === "object" ? pedidosEstados : {};
  const getCount = (key) => {
    const value = Number(estados[key] ?? 0);
    return Number.isFinite(value) ? value : 0;
  };

  const pendiente = getCount("pendiente");
  const procesando = getCount("procesando");
  const listo = getCount("listo");
  const entregado = getCount("entregado");
  const cancelado = getCount("cancelado");
  const activos = pendiente + procesando + listo;

  const setText = (selector, value) => {
    const node = document.querySelector(selector);
    if (node) {
      node.textContent = String(value);
    }
  };

  setText("#home-estado-pendiente", pendiente);
  setText("#home-estado-procesando", procesando);
  setText("#home-estado-listo", listo);
  setText("#home-estado-entregado", entregado);
  setText("#home-estado-cancelado", cancelado);
  setText("#home-estado-activos", activos);
}

function renderHomeUltimaVenta(ultimasVentas) {
  const rows = Array.isArray(ultimasVentas) ? ultimasVentas : [];
  const latest = rows.length > 0 ? rows[0] : null;

  const totalNode = document.querySelector("#home-ultima-venta-total");
  const clienteNode = document.querySelector("#home-ultima-venta-cliente");
  const fechaNode = document.querySelector("#home-ultima-venta-fecha");

  if (totalNode) {
    totalNode.textContent = latest ? formatMoney(Number(latest.total ?? 0)) : "$0.00";
  }
  if (clienteNode) {
    const clientName = latest ? `${latest.nombre ?? ""} ${latest.apellido ?? ""}`.trim() : "";
    clienteNode.textContent = clientName || "Sin ventas registradas";
  }
  if (fechaNode) {
    fechaNode.textContent = latest ? String(latest.fecha_venta ?? "-") : "-";
  }
}

async function ensureChartJs() {
  if (typeof Chart !== "undefined") {
    return true;
  }

  const scriptId = "runtime-chartjs-loader";
  const existing = document.getElementById(scriptId);
  if (existing) {
    return new Promise((resolve) => {
      existing.addEventListener("load", () => resolve(typeof Chart !== "undefined"), { once: true });
      existing.addEventListener("error", () => resolve(false), { once: true });
    });
  }

  return new Promise((resolve) => {
    const script = document.createElement("script");
    script.id = scriptId;
    script.src = "https://cdn.jsdelivr.net/npm/chart.js";
    script.async = true;
    script.onload = () => resolve(typeof Chart !== "undefined");
    script.onerror = () => resolve(false);
    document.head.appendChild(script);
  });
}

function renderHomeDashboard(data) {
  const totalVentasNode = document.querySelector("#home-total-ventas");
  const totalPedidosNode = document.querySelector("#home-total-pedidos");
  const ingresosNode = document.querySelector("#home-ingresos-totales");
  const productoLiderNode = document.querySelector("#home-producto-lider");
  const updatedAtNode = document.querySelector("#home-updated-at");
  const periodoIngresos = normalizeHomeIngresosPeriodo(data.periodoIngresos ?? homeDashboardState.ingresosPeriodo);

  homeDashboardState.ingresosPeriodo = periodoIngresos;
  renderHomeIngresosHead(periodoIngresos);

  if (totalVentasNode) {
    totalVentasNode.textContent = String(Number(data.totalVentas ?? 0));
  }
  if (totalPedidosNode) {
    totalPedidosNode.textContent = String(Number(data.totalPedidos ?? 0));
  }
  if (ingresosNode) {
    ingresosNode.textContent = formatMoney(Number(data.ingresosTotales ?? 0));
  }
  if (productoLiderNode) {
    const topProducto = Array.isArray(data.topProductos) && data.topProductos.length > 0
      ? String(data.topProductos[0].nombre_producto ?? "Sin datos")
      : "Sin datos";
    productoLiderNode.textContent = topProducto;
  }
  if (updatedAtNode) {
    updatedAtNode.textContent = String(data.ultimaActualizacion ?? new Date().toISOString().slice(0, 19).replace("T", " "));
  }

  renderHomeCarteraSummary(data.carteraResumen);
  renderHomeHistorialSummary(data.historialResumen);
  renderHomeUltimaVenta(data.ultimasVentas);
  renderHomePedidosEstadoSummary(data.pedidosEstados);
  renderHomeTopProductos(data.topProductos);
  renderHomeUltimasVentas(data.ultimasVentas);
  renderHomeClientesConDeuda(data.clientesConDeuda);
  renderHomeUltimosHistorial(data.ultimosHistorial);
  renderHomeCharts(data);
  initTablePagination(content);
}

async function requestHomeDashboardData() {
  const periodo = normalizeHomeIngresosPeriodo(homeDashboardState.ingresosPeriodo);
  const query = new URLSearchParams({
    route: "home-data",
    periodo
  });

  const response = await fetch(`?${query.toString()}`);
  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("Respuesta inválida en métricas del dashboard.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudieron obtener métricas del dashboard.");
  }

  return data.data || {};
}

async function refreshHomeDashboardData() {
  if (homeDashboardState.isFetching) return;
  homeDashboardState.isFetching = true;

  try {
    const data = await requestHomeDashboardData();
    renderHomeDashboard(data);
  } catch (error) {
    // Silenciar en UI para no bloquear el panel; dejar traza en consola.
    console.error(error);
  } finally {
    homeDashboardState.isFetching = false;
  }
}

async function initHomeDashboardRealtime() {
  if (!content?.querySelector('[data-home-dashboard="1"]')) {
    return;
  }

  destroyHomeDashboardRealtime();

  const chartReady = await ensureChartJs();
  if (!chartReady) {
    const updatedAtNode = document.querySelector("#home-updated-at");
    if (updatedAtNode) {
      updatedAtNode.textContent = "Error cargando librería de gráficos";
    }
  }

  const initialData = getHomeDashboardInitialData();
  if (initialData) {
    homeDashboardState.ingresosPeriodo = normalizeHomeIngresosPeriodo(initialData.periodoIngresos ?? "mes");
    renderHomeDashboard(initialData);
  } else {
    await refreshHomeDashboardData();
  }

  homeDashboardState.intervalId = window.setInterval(async () => {
    if (!content?.querySelector('[data-home-dashboard="1"]')) {
      destroyHomeDashboardRealtime();
      return;
    }
    await refreshHomeDashboardData();
  }, 10000);
}

document.querySelectorAll(".nav-list a").forEach((link) => {
  link.addEventListener("click", async (e) => {
    e.preventDefault();

    const a = e.target.closest("a");
    const page = a?.dataset?.page;

    if (!page) return;
    await loadRouteContent(page);
  });
});

if (typeof mobileSidebarBreakpoint?.addEventListener === "function") {
  mobileSidebarBreakpoint.addEventListener("change", handleSidebarViewportChange);
} else if (typeof mobileSidebarBreakpoint?.addListener === "function") {
  mobileSidebarBreakpoint.addListener(handleSidebarViewportChange);
}

setDashboardCurrentPage("home");
handleSidebarViewportChange();
menuBtnChange();

if (content && !content.innerHTML.trim()) {
  loadRouteContent("home").catch((error) => console.error(error));
}

document.addEventListener("visibilitychange", () => {
  if (document.visibilityState === "visible") {
    syncSharedUiPreferences(true);
  }
});

window.addEventListener("focus", () => {
  syncSharedUiPreferences(true);
});

function openPedidoModal() {
  const modal = document.querySelector("#pedido-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closePedidoModal() {
  const modal = document.querySelector("#pedido-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openPedidoActionsModal() {
  const modal = document.querySelector("#pedido-actions-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closePedidoActionsModal() {
  const modal = document.querySelector("#pedido-actions-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openPedidoEditModal() {
  const modal = document.querySelector("#pedido-edit-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closePedidoEditModal() {
  const modal = document.querySelector("#pedido-edit-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openPedidoCreateModal() {
  const modal = document.querySelector("#pedido-create-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closePedidoCreateModal() {
  const modal = document.querySelector("#pedido-create-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openClienteCreateModal() {
  const modal = document.querySelector("#cliente-create-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeClienteCreateModal() {
  const modal = document.querySelector("#cliente-create-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openClienteEditModal() {
  const modal = document.querySelector("#cliente-edit-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeClienteEditModal() {
  const modal = document.querySelector("#cliente-edit-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openProductoCreateModal() {
  const modal = document.querySelector("#producto-create-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeProductoCreateModal() {
  const modal = document.querySelector("#producto-create-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openProductoEditModal() {
  const modal = document.querySelector("#producto-edit-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeProductoEditModal() {
  const modal = document.querySelector("#producto-edit-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openVentaCreateModal() {
  const modal = document.querySelector("#venta-create-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeVentaCreateModal() {
  const modal = document.querySelector("#venta-create-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openVentaEditModal() {
  const modal = document.querySelector("#venta-edit-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeVentaEditModal() {
  const modal = document.querySelector("#venta-edit-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openVentaAbonoModal() {
  const modal = document.querySelector("#venta-abono-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeVentaAbonoModal() {
  const modal = document.querySelector("#venta-abono-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openVentaDetalleModal() {
  const modal = document.querySelector("#venta-detalle-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeVentaDetalleModal() {
  const modal = document.querySelector("#venta-detalle-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openVentaActionsModal() {
  const modal = document.querySelector("#venta-actions-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeVentaActionsModal() {
  const modal = document.querySelector("#venta-actions-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openCategoriaCreateModal() {
  const modal = document.querySelector("#categoria-create-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeCategoriaCreateModal() {
  const modal = document.querySelector("#categoria-create-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openCategoriaEditModal() {
  const modal = document.querySelector("#categoria-edit-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeCategoriaEditModal() {
  const modal = document.querySelector("#categoria-edit-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openPerfilCreateModal() {
  const modal = document.querySelector("#perfil-create-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closePerfilCreateModal() {
  const modal = document.querySelector("#perfil-create-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openPerfilEditModal() {
  const modal = document.querySelector("#perfil-edit-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closePerfilEditModal() {
  const modal = document.querySelector("#perfil-edit-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function formatMoney(value) {
  const number = Number(value || 0);
  return `$${number.toFixed(2)}`;
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function normalizeEstadoValue(value) {
  return String(value ?? "").toLowerCase().trim();
}

function toEstadoClass(value) {
  return normalizeEstadoValue(value).replace(/[^a-z0-9_-]/g, "") || "desconocido";
}

function getPaymentStatusClass(value) {
  const estado = normalizeEstadoValue(value);
  if (estado === "pagado") return "payment-pagado";
  if (estado === "parcial") return "payment-parcial";
  return "payment-pendiente";
}

function capitalize(value) {
  const text = String(value ?? "");
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : "";
}

async function requestPedidoDetail(idPedido) {
  const response = await fetch(`?route=pedido-detalle&id=${encodeURIComponent(idPedido)}`);
  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();

  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo cargar el detalle del pedido.");
  }

  return data;
}

function renderPedidoDetalleMedidas(item) {
  const medidas = Array.isArray(item?.medidas) ? item.medidas : [];
  if (medidas.length === 0) {
    return "-";
  }

  return medidas
    .map((registro) => {
      const nombre = String(registro?.nombre_persona ?? "").trim() || "Sin nombre";
      const cantidad = Number(registro?.cantidad ?? 0);
      const referencia = String(registro?.referencia ?? "").trim();
      const medidasTexto = String(registro?.medidas ?? "").trim();

      const partes = [
        `<strong>${escapeHtml(nombre)}</strong>${cantidad > 0 ? ` (x${escapeHtml(cantidad)})` : ""}`
      ];

      if (referencia) {
        partes.push(`Ref: ${escapeHtml(referencia)}`);
      }

      if (medidasTexto) {
        partes.push(escapeHtml(medidasTexto));
      }

      return `<span class="pedido-detalle-medida-linea">${partes.join(" | ")}</span>`;
    })
    .join("");
}

async function loadPedidoDetail(idPedido) {
  const data = await requestPedidoDetail(idPedido);

  const pedido = data.pedido || {};
  const detalles = Array.isArray(data.detalles) ? data.detalles : [];

  const cliente = `${pedido.nombre ?? ""} ${pedido.apellido ?? ""}`.trim();
  document.querySelector("#pedido-detalle-cliente").textContent = cliente || "-";
  document.querySelector("#pedido-detalle-cedula").textContent = pedido.cedula ?? "-";
  document.querySelector("#pedido-detalle-estado").textContent = pedido.estado ?? "-";
  document.querySelector("#pedido-detalle-fecha").textContent = pedido.fecha_creacion ?? "-";
  document.querySelector("#pedido-detalle-total").textContent = formatMoney(pedido.total);

  const itemsContainer = document.querySelector("#pedido-detalle-items");
  if (!itemsContainer) return;

  if (detalles.length === 0) {
    itemsContainer.innerHTML = `
      <tr>
        <td colspan="5" style="text-align:center;">Este pedido no tiene productos registrados.</td>
      </tr>
    `;
    initTablePagination(content);
    return;
  }

  itemsContainer.innerHTML = detalles
    .map((item) => `
      <tr>
        <td data-label="Producto">${escapeHtml(item.nombre_producto ?? "-")}</td>
        <td data-label="Cantidad">${escapeHtml(item.cantidad ?? 0)}</td>
        <td data-label="Precio Unit.">${formatMoney(item.precio_unitario)}</td>
        <td data-label="Subtotal">${formatMoney(item.subtotal)}</td>
        <td class="pedido-detalle-medidas" data-label="Medidas">${renderPedidoDetalleMedidas(item)}</td>
      </tr>
    `)
    .join("");
  initTablePagination(content);
}

function fillPedidoMedidaRow(medidaRow, medida = {}) {
  if (!medidaRow) return;

  const nombre = medidaRow.querySelector(".pedido-medida-nombre");
  const referencia = medidaRow.querySelector(".pedido-medida-referencia");
  const cantidad = medidaRow.querySelector(".pedido-medida-cantidad");
  const medidas = medidaRow.querySelector(".pedido-medida-texto");

  if (nombre) nombre.value = String(medida.nombre_persona ?? "");
  if (referencia) referencia.value = String(medida.referencia ?? "");
  if (cantidad) cantidad.value = String(Math.max(1, Number(medida.cantidad ?? 1) || 1));
  if (medidas) medidas.value = String(medida.medidas ?? "");
}

function fillPedidoItemRow(itemRow, item = {}) {
  if (!itemRow) return;

  const select = itemRow.querySelector(".pedido-item-producto");
  const cantidad = itemRow.querySelector(".pedido-item-cantidad");
  if (select) select.value = String(item.id_producto ?? "");
  if (cantidad) cantidad.value = String(Math.max(1, Number(item.cantidad ?? 1) || 1));

  resetPedidoMedidasInItem(itemRow);
  const medidas = Array.isArray(item.medidas) ? item.medidas : [];
  while (getPedidoMedidaRows(itemRow).length < Math.max(medidas.length, 1)) {
    addPedidoMedidaRowToItem(itemRow);
  }

  const medidaRows = getPedidoMedidaRows(itemRow);
  medidaRows.forEach((row, index) => {
    const medida = medidas[index] ?? null;
    if (!medida) {
      resetPedidoMedidaRow(row);
      return;
    }
    fillPedidoMedidaRow(row, medida);
  });
}

function fillPedidoEditForm(data) {
  const config = getPedidoFormConfigByFormId("pedido-edit-form");
  const pedido = data?.pedido && typeof data.pedido === "object" ? data.pedido : {};
  const detalles = Array.isArray(data?.detalles) ? data.detalles : [];
  const idInput = document.querySelector("#pedido-edit-id");
  const clienteSelect = document.querySelector(config.clienteSelector);
  const estadoSelect = document.querySelector(config.estadoSelector);
  const submitBtn = document.querySelector(config.submitSelector);

  if (!idInput || !clienteSelect || !estadoSelect || !submitBtn) return;

  resetPedidoEditForm();

  idInput.value = String(pedido.id ?? "");
  clienteSelect.value = String(pedido.id_cliente ?? "");

  const estadoActual = normalizeEstadoValue(pedido.estado);
  if ([...estadoSelect.options].some((opt) => opt.value === estadoActual)) {
    estadoSelect.value = estadoActual;
  } else {
    estadoSelect.value = estadoActual === "cancelado" ? "entregado" : "procesando";
  }

  const container = document.querySelector(config.containerSelector);
  if (!container) return;

  const firstRow = container.querySelector(".pedido-item-row");
  if (!firstRow) return;

  const rows = getPedidoItemRows(config.containerSelector);
  rows.forEach((row, index) => {
    if (index > 0) {
      row.remove();
    }
  });

  if (detalles.length === 0) {
    fillPedidoItemRow(firstRow, {});
  } else {
    fillPedidoItemRow(firstRow, detalles[0] || {});
    for (let index = 1; index < detalles.length; index += 1) {
      addPedidoItemRow(config.containerSelector, config.totalSelector);
      const currentRows = getPedidoItemRows(config.containerSelector);
      fillPedidoItemRow(currentRows[index], detalles[index] || {});
    }
  }

  submitBtn.disabled = false;
  submitBtn.textContent = config.submitText;
  setPedidoEditFeedback("", "error");
  updatePedidoEditTotal();
}

function setPedidoEditFeedback(message, type = "error") {
  const feedback = document.querySelector("#pedido-edit-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setPedidoCreateFeedback(message, type = "error") {
  const feedback = document.querySelector("#pedido-create-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setClienteCreateFeedback(message, type = "error") {
  const feedback = document.querySelector("#cliente-create-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setClienteEditFeedback(message, type = "error") {
  const feedback = document.querySelector("#cliente-edit-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setPerfilUpdateFeedback(message, type = "error") {
  const feedback = document.querySelector("#perfil-update-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setPerfilCreateFeedback(message, type = "error") {
  const feedback = document.querySelector("#perfil-create-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setPerfilEditFeedback(message, type = "error") {
  const feedback = document.querySelector("#perfil-edit-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function getPerfilUpdatePayload() {
  const id = Number(document.querySelector("#perfil-update-form [name='id']")?.value ?? 0);
  const usuario = String(document.querySelector("#perfil-update-usuario")?.value ?? "").trim();
  const correo = String(document.querySelector("#perfil-update-correo")?.value ?? "").trim();
  const contrasena = String(document.querySelector("#perfil-update-contrasena")?.value ?? "").trim();
  return { id, usuario, correo, contrasena };
}

function getPerfilCreatePayload() {
  const usuario = String(document.querySelector("#perfil-create-usuario")?.value ?? "").trim();
  const correo = String(document.querySelector("#perfil-create-correo")?.value ?? "").trim();
  const contrasena = String(document.querySelector("#perfil-create-contrasena")?.value ?? "").trim();
  const idRol = Number(document.querySelector("#perfil-create-id-rol")?.value ?? 0);
  const estado = normalizeEstadoValue(document.querySelector("#perfil-create-estado")?.value ?? "activo");
  return { usuario, correo, contrasena, idRol, estado };
}

function getPerfilEditPayload() {
  const id = Number(document.querySelector("#perfil-edit-id")?.value ?? 0);
  const usuario = String(document.querySelector("#perfil-edit-usuario")?.value ?? "").trim();
  const correo = String(document.querySelector("#perfil-edit-correo")?.value ?? "").trim();
  const contrasena = String(document.querySelector("#perfil-edit-contrasena")?.value ?? "").trim();
  const idRol = Number(document.querySelector("#perfil-edit-id-rol")?.value ?? 0);
  const estado = normalizeEstadoValue(document.querySelector("#perfil-edit-estado")?.value ?? "activo");
  return { id, usuario, correo, contrasena, idRol, estado };
}

function fillPerfilEditForm(data) {
  const idNode = document.querySelector("#perfil-edit-id");
  const usuarioNode = document.querySelector("#perfil-edit-usuario");
  const correoNode = document.querySelector("#perfil-edit-correo");
  const contrasenaNode = document.querySelector("#perfil-edit-contrasena");
  const rolNode = document.querySelector("#perfil-edit-id-rol");
  const estadoNode = document.querySelector("#perfil-edit-estado");
  const submitBtn = document.querySelector("#perfil-edit-submit");

  if (idNode) idNode.value = String(data.id ?? "");
  if (usuarioNode) usuarioNode.value = String(data.usuario ?? "");
  if (correoNode) correoNode.value = String(data.correo ?? "");
  if (contrasenaNode) contrasenaNode.value = "";
  if (rolNode) rolNode.value = String(data.idRol ?? "");
  if (estadoNode) estadoNode.value = String(normalizeEstadoValue(data.estado ?? "activo"));

  setPerfilEditFeedback("", "error");
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Guardar Cambios";
  }
}

function resetPerfilCreateForm() {
  const form = document.querySelector("#perfil-create-form");
  const submitBtn = document.querySelector("#perfil-create-submit");

  if (form) {
    form.reset();
  }

  setPerfilCreateFeedback("", "error");

  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Guardar Usuario";
  }
}

async function requestPerfilCreate(payload) {
  const params = new URLSearchParams();
  params.append("usuario", payload.usuario);
  params.append("correo", payload.correo);
  params.append("contrasena", payload.contrasena);
  params.append("id_rol", String(payload.idRol));
  params.append("estado", payload.estado);

  const response = await fetch("?route=perfil-crear", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo crear el perfil.");
  }

  return data;
}

async function requestPerfilUpdate(payload) {
  const params = new URLSearchParams();
  if (payload.id > 0) {
    params.append("id", String(payload.id));
  }
  params.append("usuario", payload.usuario);
  params.append("correo", payload.correo);
  params.append("contrasena", payload.contrasena);
  if (payload.idRol > 0) {
    params.append("id_rol", String(payload.idRol));
  }
  if (payload.estado) {
    params.append("estado", payload.estado);
  }

  const response = await fetch("?route=perfil-actualizar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo actualizar el perfil.");
  }

  return data;
}

async function requestPerfilDelete(idUsuario) {
  const params = new URLSearchParams();
  if (idUsuario > 0) {
    params.append("id", String(idUsuario));
  }

  const response = await fetch("?route=perfil-eliminar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo desactivar el perfil.");
  }

  return data;
}

function fillClienteEditForm(data) {
  const id = String(data.id ?? "");
  const nombre = String(data.nombre ?? "");
  const apellido = String(data.apellido ?? "");
  const cedula = String(data.cedula ?? "");
  const telefono = String(data.telefono ?? "");
  const direccion = String(data.direccion ?? "");
  const empresa = String(data.empresa ?? "");

  const idNode = document.querySelector("#cliente-edit-id");
  const nombreNode = document.querySelector("#cliente-edit-nombre");
  const apellidoNode = document.querySelector("#cliente-edit-apellido");
  const cedulaNode = document.querySelector("#cliente-edit-cedula");
  const telefonoNode = document.querySelector("#cliente-edit-telefono");
  const direccionNode = document.querySelector("#cliente-edit-direccion");
  const empresaNode = document.querySelector("#cliente-edit-empresa");
  const submitBtn = document.querySelector("#cliente-edit-submit");

  if (idNode) idNode.value = id;
  if (nombreNode) nombreNode.value = nombre;
  if (apellidoNode) apellidoNode.value = apellido;
  if (cedulaNode) cedulaNode.value = cedula === "-" ? "" : cedula;
  if (telefonoNode) telefonoNode.value = telefono === "-" ? "" : telefono;
  if (direccionNode) direccionNode.value = direccion === "-" ? "" : direccion;
  if (empresaNode) empresaNode.value = empresa === "-" ? "" : empresa;

  setClienteEditFeedback("", "error");
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Guardar Cambios";
  }
}

function getClienteCreatePayload() {
  const nombre = String(document.querySelector("#cliente-nombre")?.value ?? "").trim();
  const apellido = String(document.querySelector("#cliente-apellido")?.value ?? "").trim();
  const cedula = String(document.querySelector("#cliente-cedula")?.value ?? "").trim();
  const telefono = String(document.querySelector("#cliente-telefono")?.value ?? "").trim();
  const direccion = String(document.querySelector("#cliente-direccion")?.value ?? "").trim();
  const empresa = String(document.querySelector("#cliente-empresa")?.value ?? "").trim();

  return { nombre, apellido, cedula, telefono, direccion, empresa };
}

function getClienteEditPayload() {
  const id = Number(document.querySelector("#cliente-edit-id")?.value ?? 0);
  const nombre = String(document.querySelector("#cliente-edit-nombre")?.value ?? "").trim();
  const apellido = String(document.querySelector("#cliente-edit-apellido")?.value ?? "").trim();
  const cedula = String(document.querySelector("#cliente-edit-cedula")?.value ?? "").trim();
  const telefono = String(document.querySelector("#cliente-edit-telefono")?.value ?? "").trim();
  const direccion = String(document.querySelector("#cliente-edit-direccion")?.value ?? "").trim();
  const empresa = String(document.querySelector("#cliente-edit-empresa")?.value ?? "").trim();

  return { id, nombre, apellido, cedula, telefono, direccion, empresa };
}

function resetClienteCreateForm() {
  const form = document.querySelector("#cliente-create-form");
  const submitBtn = document.querySelector("#cliente-create-submit");

  if (form) {
    form.reset();
  }

  setClienteCreateFeedback("", "error");

  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Guardar Cliente";
  }
}

async function requestClienteUpdate(payload) {
  const params = new URLSearchParams();
  params.append("id", String(payload.id));
  params.append("nombre", payload.nombre);
  params.append("apellido", payload.apellido);
  params.append("cedula", payload.cedula);
  params.append("telefono", payload.telefono);
  params.append("direccion", payload.direccion);
  params.append("empresa", payload.empresa);

  const response = await fetch("?route=cliente-actualizar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo actualizar el cliente.");
  }

  return data;
}

async function requestClienteCreate(payload) {
  const params = new URLSearchParams();
  params.append("nombre", payload.nombre);
  params.append("apellido", payload.apellido);
  params.append("cedula", payload.cedula);
  params.append("telefono", payload.telefono);
  params.append("direccion", payload.direccion);
  params.append("empresa", payload.empresa);

  const response = await fetch("?route=cliente-crear", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo crear el cliente.");
  }

  return data;
}

async function requestClienteDelete(idCliente) {
  const params = new URLSearchParams();
  params.append("id", String(idCliente));

  const response = await fetch("?route=cliente-eliminar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo eliminar el cliente.");
  }

  return data;
}

function setVentaCreateFeedback(message, type = "error") {
  const feedback = document.querySelector("#venta-create-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setVentaEditFeedback(message, type = "error") {
  const feedback = document.querySelector("#venta-edit-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setVentaAbonoFeedback(message, type = "error") {
  const feedback = document.querySelector("#venta-abono-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setVentaDetalleFeedback(message, type = "error") {
  const feedback = document.querySelector("#venta-detalle-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function getVentaCreatePayload() {
  const idPedido = Number(document.querySelector("#venta-create-pedido")?.value ?? 0);
  const totalRaw = normalizePriceInput(document.querySelector("#venta-create-total")?.value);
  const abonoInicialRaw = normalizePriceInput(document.querySelector("#venta-create-abono-inicial")?.value);
  const total = Number(totalRaw);
  const abonoInicial = abonoInicialRaw === "" ? NaN : Number(abonoInicialRaw);
  const metodoPago = normalizeEstadoValue(document.querySelector("#venta-create-metodo")?.value ?? "efectivo");

  return {
    idPedido,
    totalRaw,
    total,
    abonoInicialRaw,
    abonoInicial,
    metodoPago
  };
}

function getVentaEditPayload() {
  const id = Number(document.querySelector("#venta-edit-id")?.value ?? 0);
  const totalRaw = normalizePriceInput(document.querySelector("#venta-edit-total")?.value);
  const total = Number(totalRaw);
  const metodoPago = normalizeEstadoValue(document.querySelector("#venta-edit-metodo")?.value ?? "efectivo");

  return {
    id,
    totalRaw,
    total,
    metodoPago
  };
}

function getVentaAbonoPayload() {
  const idVenta = Number(document.querySelector("#venta-abono-id-venta")?.value ?? 0);
  const montoRaw = normalizePriceInput(document.querySelector("#venta-abono-monto")?.value);
  const monto = Number(montoRaw);
  const metodoPago = normalizeEstadoValue(document.querySelector("#venta-abono-metodo")?.value ?? "efectivo");
  const observacion = String(document.querySelector("#venta-abono-observacion")?.value ?? "").trim();

  return {
    idVenta,
    montoRaw,
    monto,
    metodoPago,
    observacion
  };
}

function updateVentaCreateTotalFromPedido() {
  const select = document.querySelector("#venta-create-pedido");
  const totalNode = document.querySelector("#venta-create-total");
  const abonoInicialNode = document.querySelector("#venta-create-abono-inicial");
  if (!select || !totalNode) return;

  const option = select.options[select.selectedIndex];
  const total = Number(option?.dataset?.total ?? 0);
  if (Number.isFinite(total) && total > 0) {
    totalNode.value = total.toFixed(2);
    if (abonoInicialNode) {
      abonoInicialNode.value = total.toFixed(2);
    }
  } else if (!select.value) {
    totalNode.value = "";
    if (abonoInicialNode) {
      abonoInicialNode.value = "";
    }
  }
}

function resetVentaCreateForm() {
  const form = document.querySelector("#venta-create-form");
  const submitBtn = document.querySelector("#venta-create-submit");
  if (form) {
    form.reset();
  }

  const metodoNode = document.querySelector("#venta-create-metodo");
  if (metodoNode) metodoNode.value = "efectivo";

  setVentaCreateFeedback("", "error");
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Guardar Venta";
  }

  updateVentaCreateTotalFromPedido();
}

function syncVentaActionButton(targetSelector, sourceButton) {
  const targetButton = document.querySelector(targetSelector);
  if (!targetButton) return;

  Object.keys(targetButton.dataset).forEach((key) => {
    if (key !== "action") {
      delete targetButton.dataset[key];
    }
  });

  if (!sourceButton) {
    targetButton.hidden = true;
    targetButton.disabled = true;
    return;
  }

  Object.entries(sourceButton.dataset || {}).forEach(([key, value]) => {
    if (key !== "action") {
      targetButton.dataset[key] = value;
    }
  });

  targetButton.hidden = false;
  targetButton.disabled = false;
}

function fillVentaActionsModal(data) {
  const payload = data && typeof data === "object" ? data : {};
  const saldo = Number(payload.saldo ?? 0);
  const hintNode = document.querySelector("#venta-actions-hint");

  setNodeText("#venta-actions-id", payload.id > 0 ? `#${payload.id}` : "-");
  setNodeText("#venta-actions-pedido", payload.idPedido > 0 ? `#${payload.idPedido}` : "-");
  setNodeText("#venta-actions-cliente", payload.cliente || "-");
  setNodeText("#venta-actions-total", payload.totalLabel || "$0.00");
  setNodeText("#venta-actions-saldo", payload.saldoLabel || "$0.00");
  setNodeText("#venta-actions-pago", payload.estadoPago || "-");
  setNodeText("#venta-actions-metodo", payload.metodo || "-");
  setNodeText("#venta-actions-fecha", payload.fecha || "-");
  setNodeText("#venta-actions-estado-pedido", payload.estadoPedido || "-");

  syncVentaActionButton("#venta-actions-detalle", payload.detailButton);
  syncVentaActionButton("#venta-actions-editar", payload.editButton);
  syncVentaActionButton("#venta-actions-abono", saldo > 0 ? payload.abonoButton : null);
  syncVentaActionButton("#venta-actions-eliminar", payload.deleteButton);

  if (hintNode) {
    hintNode.textContent = saldo > 0
      ? "Puedes revisar detalle, editar la venta, registrar un abono o eliminarla."
      : "La venta ya no tiene saldo pendiente. Puedes revisar detalle, editarla o eliminarla.";
  }
}

function fillVentaAbonoForm(data) {
  const idVenta = Number(data.idVenta ?? 0);
  const idPedido = Number(data.idPedido ?? 0);
  const cliente = String(data.cliente ?? "").trim();
  const total = Number(data.total ?? 0);
  const abonado = Number(data.abonado ?? 0);
  const saldo = Number(data.saldo ?? Math.max(total - abonado, 0));

  const idInput = document.querySelector("#venta-abono-id-venta");
  const idLabel = document.querySelector("#venta-abono-id-label");
  const pedidoLabel = document.querySelector("#venta-abono-pedido-label");
  const clienteLabel = document.querySelector("#venta-abono-cliente-label");
  const totalLabel = document.querySelector("#venta-abono-total-label");
  const abonadoLabel = document.querySelector("#venta-abono-abonado-label");
  const saldoLabel = document.querySelector("#venta-abono-saldo-label");
  const montoInput = document.querySelector("#venta-abono-monto");
  const metodoNode = document.querySelector("#venta-abono-metodo");
  const observacionNode = document.querySelector("#venta-abono-observacion");
  const submitBtn = document.querySelector("#venta-abono-submit");

  if (idInput) idInput.value = String(idVenta);
  if (idLabel) idLabel.textContent = idVenta > 0 ? `#${idVenta}` : "-";
  if (pedidoLabel) pedidoLabel.textContent = idPedido > 0 ? `#${idPedido}` : "-";
  if (clienteLabel) clienteLabel.textContent = cliente || "-";
  if (totalLabel) totalLabel.textContent = formatMoney(total);
  if (abonadoLabel) abonadoLabel.textContent = formatMoney(abonado);
  if (saldoLabel) saldoLabel.textContent = formatMoney(saldo);

  if (montoInput) {
    montoInput.value = saldo > 0 ? saldo.toFixed(2) : "";
    montoInput.max = saldo > 0 ? saldo.toFixed(2) : "";
  }

  if (metodoNode) metodoNode.value = "efectivo";
  if (observacionNode) observacionNode.value = "";

  setVentaAbonoFeedback("", "error");
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Guardar Abono";
  }
}

function fillVentaEditForm(data) {
  const idNode = document.querySelector("#venta-edit-id");
  const totalNode = document.querySelector("#venta-edit-total");
  const metodoNode = document.querySelector("#venta-edit-metodo");
  const submitBtn = document.querySelector("#venta-edit-submit");

  if (idNode) idNode.value = String(data.id ?? "");
  if (totalNode) totalNode.value = String(data.total ?? "");
  if (metodoNode) metodoNode.value = String(normalizeEstadoValue(data.metodoPago ?? "efectivo"));

  setVentaEditFeedback("", "error");
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Guardar Cambios";
  }
}

function renderVentaDetalleAbonos(abonos) {
  const tbody = document.querySelector("#venta-detalle-abonos-body");
  if (!tbody) return;

  const rows = Array.isArray(abonos) ? abonos : [];
  if (rows.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="6" style="text-align:center;">Sin abonos registrados.</td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = rows.map((item) => `
    <tr>
      <td data-label="ID">#${escapeHtml(item.id ?? 0)}</td>
      <td data-label="Fecha">${escapeHtml(item.fecha_abono ?? "-")}</td>
      <td data-label="Monto">${escapeHtml(formatMoney(item.monto ?? 0))}</td>
      <td data-label="Metodo">${escapeHtml(item.metodo_pago ?? "-")}</td>
      <td data-label="Observacion">${escapeHtml(item.observacion || "-")}</td>
      <td data-label="Usuario">${escapeHtml(item.usuario_registro_nombre || "Sistema")}</td>
    </tr>
  `).join("");
}

function fillVentaDetalleModal(venta, abonos = []) {
  const ventaData = venta && typeof venta === "object" ? venta : {};
  const cliente = `${ventaData.nombre ?? ""} ${ventaData.apellido ?? ""}`.trim();
  const estadoPago = normalizeEstadoValue(ventaData.estado_pago ?? "pendiente");
  const estadoPedido = normalizeEstadoValue(ventaData.estado ?? "pendiente");
  const total = Number(ventaData.total ?? 0);
  const totalAbonado = Number(ventaData.total_abonado ?? 0);
  const saldo = Number(ventaData.saldo_pendiente ?? Math.max(total - totalAbonado, 0));

  setNodeText("#venta-detalle-id", ventaData.id ? `#${ventaData.id}` : "-");
  setNodeText("#venta-detalle-pedido", ventaData.id_pedido ? `#${ventaData.id_pedido}` : "-");
  setNodeText("#venta-detalle-cliente", cliente || "-");
  setNodeText("#venta-detalle-cedula", ventaData.cedula ?? "-");
  setNodeText("#venta-detalle-telefono", ventaData.telefono ?? "-");
  setNodeText("#venta-detalle-empresa", ventaData.empresa ?? "-");
  setNodeText("#venta-detalle-fecha", ventaData.fecha_venta ?? "-");
  setNodeText("#venta-detalle-metodo", ventaData.metodo_pago ?? "-");
  setNodeText("#venta-detalle-estado-pedido", capitalize(estadoPedido));
  setNodeText("#venta-detalle-total", formatMoney(total));
  setNodeText("#venta-detalle-abonado", formatMoney(totalAbonado));
  setNodeText("#venta-detalle-saldo", formatMoney(saldo));
  setNodeText("#venta-detalle-estado-pago", capitalize(estadoPago));
  setNodeText("#venta-detalle-ultimo-abono", ventaData.ultima_fecha_abono ?? "-");

  renderVentaDetalleAbonos(abonos);
  setVentaDetalleFeedback("", "error");
  initTablePagination(content);
}

async function requestVentaCreate(payload) {
  const params = new URLSearchParams();
  params.append("id_pedido", String(payload.idPedido));
  params.append("total", payload.totalRaw);
  if (payload.abonoInicialRaw !== "") {
    params.append("abono_inicial", payload.abonoInicialRaw);
  }
  params.append("metodo_pago", payload.metodoPago);

  const response = await fetch("?route=venta-crear", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo registrar la venta.");
  }

  return data;
}

async function requestVentaDetail(idVenta) {
  const response = await fetch(`?route=venta-detalle&id=${encodeURIComponent(idVenta)}`);
  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo cargar el detalle de la venta.");
  }

  return data;
}

async function requestVentaUpdate(payload) {
  const params = new URLSearchParams();
  params.append("id", String(payload.id));
  params.append("total", payload.totalRaw);
  params.append("metodo_pago", payload.metodoPago);

  const response = await fetch("?route=venta-actualizar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo actualizar la venta.");
  }

  return data;
}

async function requestVentaAbonoCreate(payload) {
  const params = new URLSearchParams();
  params.append("id_venta", String(payload.idVenta));
  params.append("monto", payload.montoRaw);
  params.append("metodo_pago", payload.metodoPago);
  params.append("observacion", payload.observacion);

  const response = await fetch("?route=venta-abono-crear", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo registrar el abono.");
  }

  return data;
}

async function requestVentaDelete(idVenta) {
  const params = new URLSearchParams();
  params.append("id", String(idVenta));

  const response = await fetch("?route=venta-eliminar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo eliminar la venta.");
  }

  return data;
}

function setProductoCreateFeedback(message, type = "error") {
  const feedback = document.querySelector("#producto-create-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setProductoEditFeedback(message, type = "error") {
  const feedback = document.querySelector("#producto-edit-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function normalizePriceInput(value) {
  return String(value ?? "").replace(",", ".").trim();
}

function getProductoCreatePayload() {
  const idCategoria = String(document.querySelector("#producto-create-categoria")?.value ?? "").trim();
  const nombreProducto = String(document.querySelector("#producto-create-nombre")?.value ?? "").trim();
  const descripcion = String(document.querySelector("#producto-create-descripcion")?.value ?? "").trim();
  const precioBaseRaw = normalizePriceInput(document.querySelector("#producto-create-precio")?.value);
  const precioBase = Number(precioBaseRaw);
  const stockActualRaw = String(document.querySelector("#producto-create-stock-actual")?.value ?? "0").trim();
  const stockMinimoRaw = String(document.querySelector("#producto-create-stock-minimo")?.value ?? "5").trim();
  const stockActual = Number(stockActualRaw);
  const stockMinimo = Number(stockMinimoRaw);
  const estado = normalizeEstadoValue(document.querySelector("#producto-create-estado")?.value ?? "activo");

  return {
    idCategoria,
    nombreProducto,
    descripcion,
    precioBaseRaw,
    precioBase,
    stockActualRaw,
    stockMinimoRaw,
    stockActual,
    stockMinimo,
    estado
  };
}

function getProductoEditPayload() {
  const id = Number(document.querySelector("#producto-edit-id")?.value ?? 0);
  const idCategoria = String(document.querySelector("#producto-edit-categoria")?.value ?? "").trim();
  const nombreProducto = String(document.querySelector("#producto-edit-nombre")?.value ?? "").trim();
  const descripcion = String(document.querySelector("#producto-edit-descripcion")?.value ?? "").trim();
  const precioBaseRaw = normalizePriceInput(document.querySelector("#producto-edit-precio")?.value);
  const precioBase = Number(precioBaseRaw);
  const stockActualRaw = String(document.querySelector("#producto-edit-stock-actual")?.value ?? "0").trim();
  const stockMinimoRaw = String(document.querySelector("#producto-edit-stock-minimo")?.value ?? "5").trim();
  const stockActual = Number(stockActualRaw);
  const stockMinimo = Number(stockMinimoRaw);
  const estado = normalizeEstadoValue(document.querySelector("#producto-edit-estado")?.value ?? "activo");

  return {
    id,
    idCategoria,
    nombreProducto,
    descripcion,
    precioBaseRaw,
    precioBase,
    stockActualRaw,
    stockMinimoRaw,
    stockActual,
    stockMinimo,
    estado
  };
}

function resetProductoCreateForm() {
  const form = document.querySelector("#producto-create-form");
  const submitBtn = document.querySelector("#producto-create-submit");

  if (form) {
    form.reset();
  }

  const estadoNode = document.querySelector("#producto-create-estado");
  const stockActualNode = document.querySelector("#producto-create-stock-actual");
  const stockMinimoNode = document.querySelector("#producto-create-stock-minimo");
  if (estadoNode) estadoNode.value = "activo";
  if (stockActualNode) stockActualNode.value = "0";
  if (stockMinimoNode) stockMinimoNode.value = "5";

  setProductoCreateFeedback("", "error");
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Guardar Producto";
  }
}

function fillProductoEditForm(data) {
  const idNode = document.querySelector("#producto-edit-id");
  const categoriaNode = document.querySelector("#producto-edit-categoria");
  const nombreNode = document.querySelector("#producto-edit-nombre");
  const descripcionNode = document.querySelector("#producto-edit-descripcion");
  const precioNode = document.querySelector("#producto-edit-precio");
  const stockActualNode = document.querySelector("#producto-edit-stock-actual");
  const stockMinimoNode = document.querySelector("#producto-edit-stock-minimo");
  const estadoNode = document.querySelector("#producto-edit-estado");
  const submitBtn = document.querySelector("#producto-edit-submit");

  if (idNode) idNode.value = String(data.id ?? "");
  if (categoriaNode) categoriaNode.value = String(data.idCategoria ?? "");
  if (nombreNode) nombreNode.value = String(data.nombreProducto ?? "");
  if (descripcionNode) descripcionNode.value = String(data.descripcion ?? "");
  if (precioNode) precioNode.value = String(data.precioBase ?? "");
  if (stockActualNode) stockActualNode.value = String(data.stockActual ?? "0");
  if (stockMinimoNode) stockMinimoNode.value = String(data.stockMinimo ?? "5");
  if (estadoNode) {
    const estado = normalizeEstadoValue(data.estado ?? "activo");
    estadoNode.value = (estado === "inactivo") ? "inactivo" : "activo";
  }

  setProductoEditFeedback("", "error");
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Guardar Cambios";
  }
}

async function requestProductoCreate(payload) {
  const params = new URLSearchParams();
  params.append("id_categoria", payload.idCategoria);
  params.append("nombre_producto", payload.nombreProducto);
  params.append("descripcion", payload.descripcion);
  params.append("precio_base", payload.precioBaseRaw);
  params.append("stock_actual", payload.stockActualRaw);
  params.append("stock_minimo", payload.stockMinimoRaw);
  params.append("estado", payload.estado);

  const response = await fetch("?route=producto-crear", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo crear el producto.");
  }

  return data;
}

async function requestProductoUpdate(payload) {
  const params = new URLSearchParams();
  params.append("id", String(payload.id));
  params.append("id_categoria", payload.idCategoria);
  params.append("nombre_producto", payload.nombreProducto);
  params.append("descripcion", payload.descripcion);
  params.append("precio_base", payload.precioBaseRaw);
  params.append("stock_actual", payload.stockActualRaw);
  params.append("stock_minimo", payload.stockMinimoRaw);
  params.append("estado", payload.estado);

  const response = await fetch("?route=producto-actualizar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo actualizar el producto.");
  }

  return data;
}

async function requestProductoDelete(idProducto) {
  const params = new URLSearchParams();
  params.append("id", String(idProducto));

  const response = await fetch("?route=producto-eliminar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo eliminar el producto.");
  }

  return data;
}

function setCategoriaCreateFeedback(message, type = "error") {
  const feedback = document.querySelector("#categoria-create-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function setCategoriaEditFeedback(message, type = "error") {
  const feedback = document.querySelector("#categoria-edit-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function getCategoriaCreatePayload() {
  const tipoCategoria = String(document.querySelector("#categoria-create-tipo")?.value ?? "").trim();
  const estado = normalizeEstadoValue(document.querySelector("#categoria-create-estado")?.value ?? "activo");

  return {
    tipoCategoria,
    estado
  };
}

function getCategoriaEditPayload() {
  const id = Number(document.querySelector("#categoria-edit-id")?.value ?? 0);
  const tipoCategoria = String(document.querySelector("#categoria-edit-tipo")?.value ?? "").trim();
  const estado = normalizeEstadoValue(document.querySelector("#categoria-edit-estado")?.value ?? "activo");

  return {
    id,
    tipoCategoria,
    estado
  };
}

function resetCategoriaCreateForm() {
  const form = document.querySelector("#categoria-create-form");
  const submitBtn = document.querySelector("#categoria-create-submit");

  if (form) {
    form.reset();
  }

  const estadoNode = document.querySelector("#categoria-create-estado");
  if (estadoNode) estadoNode.value = "activo";

  setCategoriaCreateFeedback("", "error");
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Guardar Categoría";
  }
}

function fillCategoriaEditForm(data) {
  const idNode = document.querySelector("#categoria-edit-id");
  const tipoNode = document.querySelector("#categoria-edit-tipo");
  const estadoNode = document.querySelector("#categoria-edit-estado");
  const submitBtn = document.querySelector("#categoria-edit-submit");

  if (idNode) idNode.value = String(data.id ?? "");
  if (tipoNode) tipoNode.value = String(data.tipoCategoria ?? "");
  if (estadoNode) {
    const estado = normalizeEstadoValue(data.estado ?? "activo");
    estadoNode.value = (estado === "inactivo") ? "inactivo" : "activo";
  }

  setCategoriaEditFeedback("", "error");
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Guardar Cambios";
  }
}

async function requestCategoriaCreate(payload) {
  const params = new URLSearchParams();
  params.append("tipo_categoria", payload.tipoCategoria);
  params.append("estado", payload.estado);

  const response = await fetch("?route=categoria-crear", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo crear la categoría.");
  }

  return data;
}

async function requestCategoriaUpdate(payload) {
  const params = new URLSearchParams();
  params.append("id", String(payload.id));
  params.append("tipo_categoria", payload.tipoCategoria);
  params.append("estado", payload.estado);

  const response = await fetch("?route=categoria-actualizar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo actualizar la categoría.");
  }

  return data;
}

async function requestCategoriaDelete(idCategoria) {
  const params = new URLSearchParams();
  params.append("id", String(idCategoria));

  const response = await fetch("?route=categoria-eliminar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo eliminar la categoría.");
  }

  return data;
}

function getPedidoFormConfigByFormId(formId) {
  if (formId === "pedido-edit-form") {
    return {
      formSelector: "#pedido-edit-form",
      containerSelector: "#pedido-edit-items",
      clienteSelector: "#pedido-edit-cliente",
      estadoSelector: "#pedido-edit-estado",
      totalSelector: "#pedido-edit-total-valor",
      feedbackSelector: "#pedido-edit-feedback",
      submitSelector: "#pedido-edit-submit",
      submitText: "Guardar cambios"
    };
  }

  return {
    formSelector: "#pedido-create-form",
    containerSelector: "#pedido-items",
    clienteSelector: "#pedido-create-cliente",
    estadoSelector: "#pedido-create-estado",
    totalSelector: "#pedido-create-total-valor",
    feedbackSelector: "#pedido-create-feedback",
    submitSelector: "#pedido-create-submit",
    submitText: "Crear Pedido"
  };
}

function getPedidoFormConfigFromElement(element) {
  const form = element?.closest?.("form");
  return getPedidoFormConfigByFormId(form?.id ?? "pedido-create-form");
}

function getPedidoItemRows(containerSelector = "#pedido-items") {
  const container = document.querySelector(containerSelector);
  return container ? [...container.querySelectorAll(".pedido-item-row")] : [];
}

function getPedidoMedidaRows(itemRow) {
  if (!itemRow) return [];
  return [...itemRow.querySelectorAll(".pedido-medida-row")];
}

function resetPedidoMedidaRow(medidaRow) {
  if (!medidaRow) return;

  const nombre = medidaRow.querySelector(".pedido-medida-nombre");
  const referencia = medidaRow.querySelector(".pedido-medida-referencia");
  const cantidad = medidaRow.querySelector(".pedido-medida-cantidad");
  const medidas = medidaRow.querySelector(".pedido-medida-texto");

  if (nombre) nombre.value = "";
  if (referencia) referencia.value = "";
  if (cantidad) cantidad.value = "1";
  if (medidas) medidas.value = "";
}

function resetPedidoMedidasInItem(itemRow) {
  const medidaRows = getPedidoMedidaRows(itemRow);
  medidaRows.forEach((row, index) => {
    if (index > 0) {
      row.remove();
      return;
    }

    resetPedidoMedidaRow(row);
  });
}

function addPedidoMedidaRowToItem(itemRow) {
  if (!itemRow) return;

  const list = itemRow.querySelector(".pedido-medidas-list");
  const firstRow = list?.querySelector(".pedido-medida-row");
  if (!list || !firstRow) return;

  const clone = firstRow.cloneNode(true);
  resetPedidoMedidaRow(clone);
  list.appendChild(clone);
}

function removePedidoMedidaRow(targetButton) {
  const itemRow = targetButton?.closest(".pedido-item-row");
  if (!itemRow) return;

  const medidaRows = getPedidoMedidaRows(itemRow);
  const targetRow = targetButton.closest(".pedido-medida-row");
  if (!targetRow) return;

  if (medidaRows.length <= 1) {
    resetPedidoMedidaRow(targetRow);
    return;
  }

  targetRow.remove();
}

function getProductoPrecioFromRow(row) {
  const select = row.querySelector(".pedido-item-producto");
  if (!select) return 0;
  const option = select.options[select.selectedIndex];
  const precio = Number(option?.dataset?.precio ?? 0);
  return Number.isFinite(precio) ? precio : 0;
}

function updatePedidoFormTotal(containerSelector, totalSelector) {
  const totalNode = document.querySelector(totalSelector);
  if (!totalNode) return;

  const total = getPedidoItemRows(containerSelector).reduce((sum, row) => {
    const cantidadNode = row.querySelector(".pedido-item-cantidad");
    const cantidad = Number(cantidadNode?.value ?? 0);
    const precio = getProductoPrecioFromRow(row);
    if (!Number.isFinite(cantidad) || cantidad <= 0) return sum;
    return sum + (cantidad * precio);
  }, 0);

  totalNode.textContent = formatMoney(total);
}

function updatePedidoCreateTotal() {
  updatePedidoFormTotal("#pedido-items", "#pedido-create-total-valor");
}

function updatePedidoEditTotal() {
  updatePedidoFormTotal("#pedido-edit-items", "#pedido-edit-total-valor");
}

function addPedidoItemRow(containerSelector = "#pedido-items", totalSelector = "#pedido-create-total-valor") {
  const container = document.querySelector(containerSelector);
  const firstRow = container?.querySelector(".pedido-item-row");
  if (!container || !firstRow) return;

  const clone = firstRow.cloneNode(true);
  const select = clone.querySelector(".pedido-item-producto");
  const cantidad = clone.querySelector(".pedido-item-cantidad");

  if (select) {
    select.value = "";
  }
  if (cantidad) {
    cantidad.value = "1";
  }

  resetPedidoMedidasInItem(clone);
  container.appendChild(clone);
  updatePedidoFormTotal(containerSelector, totalSelector);
}

function removePedidoItemRow(targetButton) {
  const config = getPedidoFormConfigFromElement(targetButton);
  const rows = getPedidoItemRows(config.containerSelector);
  if (rows.length <= 1) return;

  const row = targetButton.closest(".pedido-item-row");
  if (row) {
    row.remove();
    updatePedidoFormTotal(config.containerSelector, config.totalSelector);
  }
}

function getPedidoFormPayload(config) {
  const clienteNode = document.querySelector(config.clienteSelector);
  const estadoNode = document.querySelector(config.estadoSelector);

  const idCliente = Number(clienteNode?.value ?? 0);
  const estado = normalizeEstadoValue(estadoNode?.value ?? "pendiente");

  const items = getPedidoItemRows(config.containerSelector)
    .map((row) => {
      const idProducto = Math.trunc(Number(row.querySelector(".pedido-item-producto")?.value ?? 0));
      const cantidad = Math.trunc(Number(row.querySelector(".pedido-item-cantidad")?.value ?? 0));
      const asignaciones = getPedidoMedidaRows(row)
        .map((medidaRow) => {
          const nombrePersona = String(medidaRow.querySelector(".pedido-medida-nombre")?.value ?? "").trim();
          const referencia = String(medidaRow.querySelector(".pedido-medida-referencia")?.value ?? "").trim();
          const medidas = String(medidaRow.querySelector(".pedido-medida-texto")?.value ?? "").trim();
          const cantidadMedida = Math.trunc(Number(medidaRow.querySelector(".pedido-medida-cantidad")?.value ?? 1));

          if (!nombrePersona && !referencia && !medidas) {
            return null;
          }

          return {
            nombre_persona: nombrePersona,
            referencia,
            cantidad: cantidadMedida,
            medidas
          };
        })
        .filter((asignacion) => asignacion !== null);

      return {
        id_producto: idProducto,
        cantidad,
        asignaciones
      };
    })
    .filter((item) => item.id_producto > 0 && item.cantidad > 0);

  return { idCliente, estado, items };
}

function getPedidoCreatePayload() {
  return getPedidoFormPayload(getPedidoFormConfigByFormId("pedido-create-form"));
}

function getPedidoEditPayload() {
  const config = getPedidoFormConfigByFormId("pedido-edit-form");
  const idNode = document.querySelector("#pedido-edit-id");
  const payload = getPedidoFormPayload(config);
  return {
    id: Math.trunc(Number(idNode?.value ?? 0)),
    ...payload
  };
}

function getPedidoValidationError(payload, actionLabel = "gestionar") {
  if (!payload || !Array.isArray(payload.items)) {
    return `Datos inválidos para ${actionLabel} el pedido.`;
  }

  for (let itemIndex = 0; itemIndex < payload.items.length; itemIndex += 1) {
    const item = payload.items[itemIndex];
    const cantidadProducto = Number(item.cantidad ?? 0);
    const asignaciones = Array.isArray(item.asignaciones) ? item.asignaciones : [];
    let cantidadAsignada = 0;

    for (let medidaIndex = 0; medidaIndex < asignaciones.length; medidaIndex += 1) {
      const asignacion = asignaciones[medidaIndex] || {};
      const nombrePersona = String(asignacion.nombre_persona ?? "").trim();
      const cantidadMedida = Number(asignacion.cantidad ?? 0);

      if (!nombrePersona) {
        return `En el producto #${itemIndex + 1} falta el nombre en la medida #${medidaIndex + 1}.`;
      }

      if (!Number.isFinite(cantidadMedida) || cantidadMedida <= 0) {
        return `En el producto #${itemIndex + 1} la cantidad de la medida #${medidaIndex + 1} debe ser mayor a cero.`;
      }

      cantidadAsignada += cantidadMedida;
    }

    if (cantidadAsignada > cantidadProducto) {
      return `En el producto #${itemIndex + 1} la cantidad total de medidas (${cantidadAsignada}) supera la cantidad del pedido (${cantidadProducto}).`;
    }
  }

  return "";
}

function getPedidoCreateValidationError(payload) {
  return getPedidoValidationError(payload, "crear");
}

async function requestPedidoCreate(payload) {
  const params = new URLSearchParams();
  params.append("id_cliente", String(payload.idCliente));
  params.append("estado", payload.estado);
  params.append("items", JSON.stringify(payload.items));

  const response = await fetch("?route=pedido-crear", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo crear el pedido.");
  }

  return data;
}

async function requestPedidoUpdate(payload) {
  const params = new URLSearchParams();
  params.append("id", String(payload.id));
  params.append("id_cliente", String(payload.idCliente));
  params.append("estado", payload.estado);
  params.append("items", JSON.stringify(payload.items));

  const response = await fetch("?route=pedido-actualizar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo actualizar el pedido.");
  }

  return data;
}

async function requestPedidoDelete(idPedido) {
  const params = new URLSearchParams();
  params.append("id", String(idPedido));

  const response = await fetch("?route=pedido-eliminar", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo eliminar el pedido.");
  }

  return data;
}

function resetPedidoForm(config) {
  const form = document.querySelector(config.formSelector);
  const container = document.querySelector(config.containerSelector);

  if (!form || !container) return;

  form.reset();

  const rows = getPedidoItemRows(config.containerSelector);
  rows.forEach((row, index) => {
    if (index > 0) {
      row.remove();
      return;
    }

    const select = row.querySelector(".pedido-item-producto");
    const cantidad = row.querySelector(".pedido-item-cantidad");
    if (select) select.value = "";
    if (cantidad) cantidad.value = "1";
    resetPedidoMedidasInItem(row);
  });

  const feedback = document.querySelector(config.feedbackSelector);
  if (feedback) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
  }

  const submitBtn = document.querySelector(config.submitSelector);
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = config.submitText;
  }

  updatePedidoFormTotal(config.containerSelector, config.totalSelector);
}

function resetPedidoCreateForm() {
  resetPedidoForm(getPedidoFormConfigByFormId("pedido-create-form"));
}

function resetPedidoEditForm() {
  resetPedidoForm(getPedidoFormConfigByFormId("pedido-edit-form"));
  const idInput = document.querySelector("#pedido-edit-id");
  if (idInput) {
    idInput.value = "";
  }
}

function syncPedidoActionButton(targetSelector, sourceButton) {
  const targetButton = document.querySelector(targetSelector);
  if (!targetButton) return;

  Object.keys(targetButton.dataset).forEach((key) => {
    if (key !== "action") {
      delete targetButton.dataset[key];
    }
  });

  if (!sourceButton) {
    targetButton.hidden = true;
    targetButton.disabled = true;
    return;
  }

  Object.entries(sourceButton.dataset || {}).forEach(([key, value]) => {
    if (key !== "action") {
      targetButton.dataset[key] = value;
    }
  });

  targetButton.hidden = false;
  targetButton.disabled = Boolean(sourceButton.disabled);
  targetButton.title = sourceButton.getAttribute("title") || "";
}

function fillPedidoActionsModal(data) {
  const payload = data && typeof data === "object" ? data : {};
  const hasVenta = Number(payload.idVenta ?? 0) > 0;
  const hintNode = document.querySelector("#pedido-actions-hint");

  setNodeText("#pedido-actions-id", payload.id > 0 ? `#${payload.id}` : "-");
  setNodeText("#pedido-actions-cliente", payload.cliente || "-");
  setNodeText("#pedido-actions-cedula", payload.cedula || "-");
  setNodeText("#pedido-actions-total", payload.totalLabel || "$0.00");
  setNodeText("#pedido-actions-fecha", payload.fecha || "-");
  setNodeText("#pedido-actions-estado", payload.estado || "-");
  setNodeText("#pedido-actions-venta", hasVenta ? `Venta #${payload.idVenta}` : "Sin venta");

  syncPedidoActionButton("#pedido-actions-ver", payload.viewButton);
  syncPedidoActionButton("#pedido-actions-editar", payload.editButton);
  syncPedidoActionButton("#pedido-actions-eliminar", payload.deleteButton);

  if (hintNode) {
    hintNode.textContent = hasVenta
      ? "Este pedido tiene una venta asociada. Puedes revisar o editar el pedido; la eliminación queda bloqueada por integridad."
      : "Puedes revisar el detalle, editar el pedido completo o eliminarlo si aún no tiene una venta asociada.";
  }
}

function openHistorialModal() {
  const modal = document.querySelector("#historial-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeHistorialModal() {
  const modal = document.querySelector("#historial-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function openHistorialActionsModal() {
  const modal = document.querySelector("#historial-actions-modal");
  if (modal) {
    modal.hidden = false;
  }
}

function closeHistorialActionsModal() {
  const modal = document.querySelector("#historial-actions-modal");
  if (modal) {
    modal.hidden = true;
  }
}

function isHistorialModalVisible() {
  const modal = document.querySelector("#historial-modal");
  return Boolean(modal && !modal.hidden);
}

function setHistorialFeedback(message, type = "error") {
  const feedback = document.querySelector("#historial-feedback");
  if (!feedback) return;

  if (!message) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove("error", "success");
  feedback.classList.add(type);
}

function syncHistorialActionButton(targetSelector, sourceButton) {
  const targetButton = document.querySelector(targetSelector);
  if (!targetButton) return;

  Object.keys(targetButton.dataset).forEach((key) => {
    if (key !== "action") {
      delete targetButton.dataset[key];
    }
  });

  if (!sourceButton) {
    targetButton.hidden = true;
    targetButton.disabled = true;
    return;
  }

  Object.entries(sourceButton.dataset || {}).forEach(([key, value]) => {
    if (key !== "action") {
      targetButton.dataset[key] = value;
    }
  });

  targetButton.hidden = false;
  targetButton.disabled = false;
}

function fillHistorialActionsModal(data) {
  const payload = data && typeof data === "object" ? data : {};
  const canAnular = Boolean(payload.canAnular);
  const hintNode = document.querySelector("#historial-actions-hint");

  setNodeText("#historial-actions-id", payload.id > 0 ? `#${payload.id}` : "-");
  setNodeText("#historial-actions-venta", payload.idVenta > 0 ? `#${payload.idVenta}` : "-");
  setNodeText("#historial-actions-pedido", payload.idPedido > 0 ? `#${payload.idPedido}` : "-");
  setNodeText("#historial-actions-cliente", payload.cliente || "-");
  setNodeText("#historial-actions-total", payload.totalLabel || "$0.00");
  setNodeText("#historial-actions-pago", payload.estadoPago || "-");
  setNodeText("#historial-actions-metodo", payload.metodo || "-");
  setNodeText("#historial-actions-fecha", payload.fecha || "-");
  setNodeText("#historial-actions-estado", payload.estadoHistorial || "-");

  syncHistorialActionButton("#historial-actions-ver", payload.viewButton);
  syncHistorialActionButton("#historial-actions-imprimir", payload.printButton);
  syncHistorialActionButton("#historial-actions-anular", canAnular ? payload.anularButton : null);

  if (hintNode) {
    hintNode.textContent = canAnular
      ? "Puedes revisar detalle, imprimir el comprobante o anular este registro."
      : "Este registro ya está anulado. Puedes revisar el detalle o imprimir el comprobante.";
  }
}

async function requestHistorialDetail(idHistorial) {
  const response = await fetch(`?route=historial-detalle&id=${encodeURIComponent(idHistorial)}`);
  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo cargar el historial.");
  }

  return data;
}

function setNodeText(selector, value) {
  const node = document.querySelector(selector);
  if (!node) return;
  node.textContent = String(value ?? "-");
}

function getHistorialBadgeClassByEstado(estadoValue) {
  const estado = normalizeEstadoValue(estadoValue);
  if (estado === "anulado") return "anulado";
  if (estado === "entregado") return "entregado";
  return "registrado";
}

function getPedidoChipClassByEstado(estadoValue) {
  const estado = normalizeEstadoValue(estadoValue);
  if (estado === "entregado" || estado === "listo") return "is-ok";
  if (estado === "procesando") return "is-proceso";
  if (estado === "cancelado") return "is-cancelado";
  return "is-pendiente";
}

function getPagoChipClassByEstado(estadoValue) {
  const estado = normalizeEstadoValue(estadoValue);
  if (estado === "pagado") return "pago-pagado";
  if (estado === "parcial") return "pago-parcial";
  return "pago-pendiente";
}

function updateHistorialDetailStatusUI(estadoHistorial, estadoPedido, estadoPago = "pendiente") {
  const estadoHistorialNode = document.querySelector("#historial-detalle-estado");
  if (estadoHistorialNode) {
    estadoHistorialNode.className = `badge ${getHistorialBadgeClassByEstado(estadoHistorial)}`;
  }

  const estadoPedidoNode = document.querySelector("#historial-detalle-estado-pedido");
  if (estadoPedidoNode) {
    estadoPedidoNode.className = `pedido-chip ${getPedidoChipClassByEstado(estadoPedido)}`;
  }

  const estadoPagoNode = document.querySelector("#historial-detalle-estado-pago");
  if (estadoPagoNode) {
    estadoPagoNode.className = `pago-chip ${getPagoChipClassByEstado(estadoPago)}`;
  }
}

function formatPersonalizacionesDetalle(value) {
  const text = String(value ?? "").trim();
  if (!text) return "-";

  return text
    .split(",")
    .map((part) => part.trim())
    .filter(Boolean)
    .map((part) => `- ${escapeHtml(part)}`)
    .join("<br>");
}

function renderHistorialDetalleItems(detalle) {
  const tbody = document.querySelector("#historial-detalle-items-body");
  if (!tbody) return;

  const rows = Array.isArray(detalle) ? detalle : [];
  if (rows.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="historial-empty">Sin detalle para mostrar.</td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = rows.map((item) => {
    const subtotal = Number(item.subtotal ?? 0);
    const totalExtra = Number(item.total_extra ?? 0);
    const totalLinea = subtotal + totalExtra;

    return `
      <tr>
        <td data-label="Producto">${escapeHtml(item.nombre_producto ?? "-")}</td>
        <td data-label="Cantidad">${escapeHtml(item.cantidad ?? 0)}</td>
        <td data-label="P. Unitario">${escapeHtml(formatMoney(item.precio_unitario ?? 0))}</td>
        <td data-label="Subtotal">${escapeHtml(formatMoney(subtotal))}</td>
        <td data-label="Extras">${escapeHtml(formatMoney(totalExtra))}</td>
        <td data-label="Total Línea">${escapeHtml(formatMoney(totalLinea))}</td>
        <td class="historial-personalizaciones" data-label="Personalizaciones">${formatPersonalizacionesDetalle(item.personalizaciones)}</td>
      </tr>
    `;
  }).join("");
}

function fillHistorialDetail(registro, detalle = [], resumen = {}) {
  const cliente = `${registro.nombre ?? ""} ${registro.apellido ?? ""}`.trim();
  const responsable = String(registro.usuario_responsable_nombre ?? "").trim() || "Sistema";
  const estadoHistorialRaw = normalizeEstadoValue(registro.estado || "registrado");
  const estadoPedidoRaw = normalizeEstadoValue(registro.estado_pedido || "pendiente");
  const estadoPagoRaw = normalizeEstadoValue(registro.estado_pago || "pendiente");
  const estadoHistorial = capitalize(estadoHistorialRaw);
  const estadoPedido = capitalize(estadoPedidoRaw);
  const estadoPago = capitalize(estadoPagoRaw);
  const metodoPago = capitalize(normalizeEstadoValue(registro.metodo_pago || "no definido"));

  const totalItems = Number(resumen.total_items ?? registro.total_items ?? 0);
  const totalPrendas = Number(resumen.total_prendas ?? registro.total_prendas ?? 0);
  const subtotalProductos = Number(resumen.subtotal_productos ?? 0);
  const totalExtras = Number(resumen.total_extras ?? 0);
  const totalCalculado = Number(resumen.total_calculado ?? (subtotalProductos + totalExtras));
  const totalAbonado = Number(registro.total_abonado ?? 0);
  const saldoPendiente = Number(registro.saldo_pendiente ?? Math.max(Number(registro.total ?? 0) - totalAbonado, 0));

  setNodeText("#historial-detalle-id", registro.id ?? "-");
  setNodeText("#historial-detalle-venta", registro.id_venta ?? "-");
  setNodeText("#historial-detalle-pedido", registro.id_pedido ?? "-");
  setNodeText("#historial-detalle-cliente", cliente || "-");
  setNodeText("#historial-detalle-cedula", registro.cedula ?? "-");
  setNodeText("#historial-detalle-telefono", registro.telefono ?? "-");
  setNodeText("#historial-detalle-empresa", registro.empresa ?? "-");
  setNodeText("#historial-detalle-direccion", registro.direccion ?? "-");
  setNodeText("#historial-detalle-metodo", metodoPago);
  setNodeText("#historial-detalle-responsable", responsable);
  setNodeText("#historial-detalle-fecha", registro.fecha ?? "-");
  setNodeText("#historial-detalle-fecha-venta", registro.fecha_venta ?? "-");
  setNodeText("#historial-detalle-total", formatMoney(registro.total ?? 0));
  setNodeText("#historial-detalle-abonado", formatMoney(totalAbonado));
  setNodeText("#historial-detalle-saldo", formatMoney(saldoPendiente));
  setNodeText("#historial-detalle-estado-pago", estadoPago);
  setNodeText("#historial-detalle-ultimo-abono", registro.ultima_fecha_abono ?? "-");
  setNodeText("#historial-detalle-estado", estadoHistorial);
  setNodeText("#historial-detalle-estado-pedido", estadoPedido);
  setNodeText("#historial-detalle-total-items", totalItems);
  setNodeText("#historial-detalle-total-prendas", totalPrendas);
  setNodeText("#historial-detalle-subtotal", formatMoney(subtotalProductos));
  setNodeText("#historial-detalle-extras", formatMoney(totalExtras));
  setNodeText("#historial-detalle-total-calculado", formatMoney(totalCalculado));
  updateHistorialDetailStatusUI(estadoHistorialRaw, estadoPedidoRaw, estadoPagoRaw);

  renderHistorialDetalleItems(detalle);
  initTablePagination(content);
}

async function loadHistorialDetail(idHistorial) {
  const data = await requestHistorialDetail(idHistorial);
  fillHistorialDetail(data.registro || {}, data.detalle || [], data.resumen || {});
  return data;
}

async function anularHistorialRegistro(idHistorial) {
  const params = new URLSearchParams();
  params.append("id", idHistorial);

  const response = await fetch("?route=historial-anular", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    },
    body: params.toString()
  });

  const responseType = response.headers.get("content-type") || "";
  if (!responseType.includes("application/json")) {
    throw new Error("La sesión expiró o la respuesta del servidor no es válida.");
  }

  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || "No se pudo anular el registro.");
  }

  return data;
}

function refreshHistorialRowAsAnulado(idHistorial) {
  if (!content) return;

  const row = [...content.querySelectorAll('[data-action="ver-historial"]')]
    .find((btn) => String(btn.dataset.id) === String(idHistorial))
    ?.closest("tr");

  if (!row) return;

  const badge = row.querySelector(".badge");
  if (badge) {
    badge.className = "badge anulado";
    badge.textContent = "Anulado";
  }

  row.dataset.estadoHistorial = "Anulado";

  const anularBtn = row.querySelector('[data-action="anular-historial"]');
  if (anularBtn) {
    anularBtn.remove();
  }

  const detailId = document.querySelector("#historial-detalle-id");
  const detailEstado = document.querySelector("#historial-detalle-estado");
  if (detailId && detailEstado && String(detailId.textContent ?? "") === String(idHistorial)) {
    detailEstado.className = "badge anulado";
    detailEstado.textContent = "Anulado";
  }
}

function printHistorialRegistro(registro, detalle = [], resumen = {}) {
  const cliente = `${registro.nombre ?? ""} ${registro.apellido ?? ""}`.trim();
  const responsable = String(registro.usuario_responsable_nombre ?? "").trim() || "Sistema";
  const estadoHistorial = capitalize(normalizeEstadoValue(registro.estado || "registrado"));
  const estadoPedido = capitalize(normalizeEstadoValue(registro.estado_pedido || "pendiente"));
  const metodoPago = capitalize(normalizeEstadoValue(registro.metodo_pago || "no definido"));
  const rows = Array.isArray(detalle) ? detalle : [];

  const totalItems = Number(resumen.total_items ?? registro.total_items ?? rows.length ?? 0);
  const totalPrendas = Number(resumen.total_prendas ?? registro.total_prendas ?? 0);
  const subtotalProductos = Number(resumen.subtotal_productos ?? 0);
  const totalExtras = Number(resumen.total_extras ?? 0);
  const totalCalculado = Number(resumen.total_calculado ?? (subtotalProductos + totalExtras));

  const detalleRows = rows.length > 0
    ? rows.map((item) => {
      const subtotal = Number(item.subtotal ?? 0);
      const totalExtra = Number(item.total_extra ?? 0);
      const totalLinea = subtotal + totalExtra;
      return `
        <tr>
          <td>${escapeHtml(item.nombre_producto ?? "-")}</td>
          <td>${escapeHtml(item.cantidad ?? 0)}</td>
          <td>${escapeHtml(formatMoney(item.precio_unitario ?? 0))}</td>
          <td>${escapeHtml(formatMoney(subtotal))}</td>
          <td>${escapeHtml(formatMoney(totalExtra))}</td>
          <td>${escapeHtml(formatMoney(totalLinea))}</td>
          <td>${escapeHtml(item.personalizaciones || "-")}</td>
        </tr>
      `;
    }).join("")
    : `
      <tr>
        <td colspan="7" style="text-align:center;">No hay detalle de productos para esta venta.</td>
      </tr>
    `;

  const printWindow = window.open("", "_blank", "width=800,height=700");
  if (!printWindow) {
    throw new Error("No se pudo abrir la ventana de impresión.");
  }

  const html = `<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Comprobante Historial #${escapeHtml(registro.id ?? "-")}</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; color: #111; }
    h2, h3 { margin: 0 0 10px; }
    p { margin: 5px 0; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
    .box { border: 1px solid #d2d6dc; border-radius: 8px; padding: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #d2d6dc; padding: 8px; font-size: 12px; vertical-align: top; }
    th { background: #f3f4f6; }
    .right { text-align: right; }
    .summary { margin-top: 10px; width: 320px; margin-left: auto; }
    .summary td { font-size: 12px; }
  </style>
</head>
<body>
  <h2>Factura / Comprobante de Venta</h2>
  <div class="grid">
    <div class="box">
      <h3>Datos de la Operación</h3>
      <p><strong>ID Historial:</strong> ${escapeHtml(registro.id ?? "-")}</p>
      <p><strong>ID Venta:</strong> ${escapeHtml(registro.id_venta ?? "-")}</p>
      <p><strong>ID Pedido:</strong> ${escapeHtml(registro.id_pedido ?? "-")}</p>
      <p><strong>Fecha Registro:</strong> ${escapeHtml(registro.fecha ?? "-")}</p>
      <p><strong>Fecha Venta:</strong> ${escapeHtml(registro.fecha_venta ?? "-")}</p>
      <p><strong>Método de Pago:</strong> ${escapeHtml(metodoPago)}</p>
      <p><strong>Responsable:</strong> ${escapeHtml(responsable)}</p>
      <p><strong>Estado Historial:</strong> ${escapeHtml(estadoHistorial)}</p>
      <p><strong>Estado Pedido:</strong> ${escapeHtml(estadoPedido)}</p>
    </div>

    <div class="box">
      <h3>Datos del Cliente</h3>
      <p><strong>Cliente:</strong> ${escapeHtml(cliente || "-")}</p>
      <p><strong>Cédula:</strong> ${escapeHtml(registro.cedula ?? "-")}</p>
      <p><strong>Teléfono:</strong> ${escapeHtml(registro.telefono ?? "-")}</p>
      <p><strong>Empresa:</strong> ${escapeHtml(registro.empresa ?? "-")}</p>
      <p><strong>Dirección:</strong> ${escapeHtml(registro.direccion ?? "-")}</p>
      <p><strong>Items:</strong> ${escapeHtml(totalItems)}</p>
      <p><strong>Prendas:</strong> ${escapeHtml(totalPrendas)}</p>
    </div>
  </div>

  <h3>Detalle de Productos</h3>
  <table>
    <thead>
      <tr>
        <th>Producto</th>
        <th>Cantidad</th>
        <th>P. Unitario</th>
        <th>Subtotal</th>
        <th>Extras</th>
        <th>Total Línea</th>
        <th>Personalizaciones</th>
      </tr>
    </thead>
    <tbody>
      ${detalleRows}
    </tbody>
  </table>

  <table class="summary">
    <tbody>
      <tr>
        <td><strong>Subtotal Productos</strong></td>
        <td class="right">${escapeHtml(formatMoney(subtotalProductos))}</td>
      </tr>
      <tr>
        <td><strong>Total Extras</strong></td>
        <td class="right">${escapeHtml(formatMoney(totalExtras))}</td>
      </tr>
      <tr>
        <td><strong>Total Calculado</strong></td>
        <td class="right">${escapeHtml(formatMoney(totalCalculado))}</td>
      </tr>
      <tr>
        <td><strong>Total Historial</strong></td>
        <td class="right">${escapeHtml(formatMoney(registro.total ?? 0))}</td>
      </tr>
    </tbody>
  </table>
</body>
</html>`;

  printWindow.document.open();
  printWindow.document.write(html);
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
}

if (content) {
  content.addEventListener("click", async (e) => {
    const quickNavBtn = e.target.closest("[data-nav-page]");
    if (quickNavBtn) {
      const page = String(quickNavBtn.dataset.navPage ?? "").trim();
      if (!page) return;

      try {
        await loadRouteContent(page);
      } catch (error) {
        alert(error.message);
      }
      return;
    }

    const developerSelectTableBtn = e.target.closest('[data-action="developer-select-table"]');
    if (developerSelectTableBtn) {
      const tableName = String(developerSelectTableBtn.dataset.table ?? "").trim();
      if (!tableName) return;

      setDeveloperFeedback("", "error");

      try {
        const data = await requestDeveloperData(tableName);
        renderDeveloperPanel(data);
      } catch (error) {
        setDeveloperFeedback(error.message, "error");
      }
      return;
    }

    const developerRefreshBtn = e.target.closest('[data-action="developer-refresh"]');
    if (developerRefreshBtn) {
      const originalLabel = developerRefreshBtn.textContent;
      developerRefreshBtn.disabled = true;
      developerRefreshBtn.textContent = "Actualizando...";
      setDeveloperFeedback("", "error");

      try {
        const data = await requestDeveloperData();
        renderDeveloperPanel(data);
        setDeveloperFeedback("Diagnóstico actualizado correctamente.", "success");
      } catch (error) {
        setDeveloperFeedback(error.message, "error");
      } finally {
        developerRefreshBtn.disabled = false;
        developerRefreshBtn.textContent = originalLabel;
      }
      return;
    }

    const developerToggleVentasActionsBtn = e.target.closest('[data-action="developer-toggle-ventas-actions-column"]');
    if (developerToggleVentasActionsBtn) {
      const originalLabel = developerToggleVentasActionsBtn.textContent;
      const currentlyEnabled = String(developerToggleVentasActionsBtn.dataset.enabled ?? "1") === "1";

      developerToggleVentasActionsBtn.disabled = true;
      developerToggleVentasActionsBtn.textContent = "Guardando...";
      setDeveloperFeedback("", "error");

      try {
        const result = await requestDeveloperAction("actualizar-preferencia-ui", {
          key: "ventas_show_actions_column",
          value: currentlyEnabled ? "0" : "1",
          table: getDeveloperSelectedTable()
        });

        if (result.data) {
          renderDeveloperPanel(result.data);
        }

        setDeveloperFeedback(result.message || "Preferencia actualizada correctamente.", "success");
      } catch (error) {
        setDeveloperFeedback(error.message, "error");
      } finally {
        developerToggleVentasActionsBtn.disabled = false;
        if (!content?.querySelector('[data-developer-panel="1"]')) {
          developerToggleVentasActionsBtn.textContent = originalLabel;
        }
      }
      return;
    }

    const developerToggleHistorialActionsBtn = e.target.closest('[data-action="developer-toggle-historial-actions-column"]');
    if (developerToggleHistorialActionsBtn) {
      const originalLabel = developerToggleHistorialActionsBtn.textContent;
      const currentlyEnabled = String(developerToggleHistorialActionsBtn.dataset.enabled ?? "1") === "1";

      developerToggleHistorialActionsBtn.disabled = true;
      developerToggleHistorialActionsBtn.textContent = "Guardando...";
      setDeveloperFeedback("", "error");

      try {
        const result = await requestDeveloperAction("actualizar-preferencia-ui", {
          key: "historial_show_actions_column",
          value: currentlyEnabled ? "0" : "1",
          table: getDeveloperSelectedTable()
        });

        if (result.data) {
          renderDeveloperPanel(result.data);
        }

        setDeveloperFeedback(result.message || "Preferencia actualizada correctamente.", "success");
      } catch (error) {
        setDeveloperFeedback(error.message, "error");
      } finally {
        developerToggleHistorialActionsBtn.disabled = false;
        if (!content?.querySelector('[data-developer-panel="1"]')) {
          developerToggleHistorialActionsBtn.textContent = originalLabel;
        }
      }
      return;
    }

    const developerRefreshTableBtn = e.target.closest('[data-action="developer-refresh-table"]');
    if (developerRefreshTableBtn) {
      const tableName = getDeveloperSelectedTable();
      if (!tableName) {
        setDeveloperFeedback("Debes seleccionar una tabla.", "error");
        return;
      }

      const originalLabel = developerRefreshTableBtn.textContent;
      developerRefreshTableBtn.disabled = true;
      developerRefreshTableBtn.textContent = "Actualizando...";
      setDeveloperFeedback("", "error");

      try {
        const data = await requestDeveloperData(tableName);
        renderDeveloperPanel(data);
        setDeveloperFeedback(`Tabla ${tableName} actualizada correctamente.`, "success");
      } catch (error) {
        setDeveloperFeedback(error.message, "error");
      } finally {
        developerRefreshTableBtn.disabled = false;
        developerRefreshTableBtn.textContent = originalLabel;
      }
      return;
    }

    const developerRecalculateBtn = e.target.closest('[data-action="developer-recalcular-pedidos"]');
    if (developerRecalculateBtn) {
      const confirmed = window.confirm("Se recalcularán los totales de pedidos desde el detalle. ¿Deseas continuar?");
      if (!confirmed) return;

      const originalLabel = developerRecalculateBtn.textContent;
      developerRecalculateBtn.disabled = true;
      developerRecalculateBtn.textContent = "Procesando...";
      setDeveloperFeedback("", "error");

      try {
        const result = await requestDeveloperAction("recalcular-totales-pedidos");
        if (result.data) {
          renderDeveloperPanel(result.data);
        }
        setDeveloperFeedback(result.message || "Acción ejecutada correctamente.", "success");
      } catch (error) {
        setDeveloperFeedback(error.message, "error");
      } finally {
        developerRecalculateBtn.disabled = false;
        developerRecalculateBtn.textContent = originalLabel;
      }
      return;
    }

    const developerClearTableBtn = e.target.closest('[data-action="developer-clear-table"]');
    if (developerClearTableBtn) {
      const tableName = getDeveloperSelectedTable();
      if (!tableName) {
        setDeveloperFeedback("Debes seleccionar una tabla.", "error");
        return;
      }

      const confirmed = window.confirm(`Se eliminarán todos los registros de ${tableName}. Esta acción no se puede deshacer. ¿Deseas continuar?`);
      if (!confirmed) return;

      const originalLabel = developerClearTableBtn.textContent;
      developerClearTableBtn.disabled = true;
      developerClearTableBtn.textContent = "Procesando...";
      setDeveloperFeedback("", "error");

      try {
        const result = await requestDeveloperAction("vaciar-tabla", {
          table: tableName
        });

        if (result.logout) {
          window.location.href = result.redirect || "?route=login";
          return;
        }

        if (result.data) {
          renderDeveloperPanel(result.data);
        }

        setDeveloperFeedback(result.message || "Tabla vaciada correctamente.", "success");
      } catch (error) {
        setDeveloperFeedback(error.message, "error");
      } finally {
        developerClearTableBtn.disabled = false;
        developerClearTableBtn.textContent = originalLabel;
      }
      return;
    }

    const developerResetPasswordBtn = e.target.closest('[data-action="developer-reset-password-user"]');
    if (developerResetPasswordBtn) {
      const idUsuario = Number(developerResetPasswordBtn.dataset.userId ?? 0);
      const username = String(developerResetPasswordBtn.dataset.username ?? "").trim() || "este usuario";

      if (!Number.isFinite(idUsuario) || idUsuario <= 0) {
        setDeveloperFeedback("Usuario inválido para resetear contraseña.", "error");
        return;
      }

      const confirmed = window.confirm(`Se generará una contraseña temporal para ${username}. ¿Deseas continuar?`);
      if (!confirmed) return;

      const originalLabel = developerResetPasswordBtn.textContent;
      developerResetPasswordBtn.disabled = true;
      developerResetPasswordBtn.textContent = "Procesando...";
      setDeveloperFeedback("", "error");

      try {
        const result = await requestDeveloperAction("resetear-contrasena-usuario", {
          id_usuario: idUsuario
        });

        if (result.data) {
          renderDeveloperPanel(result.data);
        }

        const temporaryPassword = String(result.temporary_password ?? "").trim();
        const feedbackMessage = result.message || "Contraseña temporal generada.";
        setDeveloperFeedback(feedbackMessage, "success");

        if (temporaryPassword) {
          window.alert(`Nueva contraseña temporal para ${username}: ${temporaryPassword}`);
        }
      } catch (error) {
        setDeveloperFeedback(error.message, "error");
      } finally {
        developerResetPasswordBtn.disabled = false;
        developerResetPasswordBtn.textContent = originalLabel;
      }
      return;
    }

    const developerEditRowBtn = e.target.closest('[data-action="developer-edit-row"]');
    if (developerEditRowBtn) {
      const primaryKey = decodeDeveloperPayload(developerEditRowBtn.dataset.primaryKey ?? "");
      if (!primaryKey || typeof primaryKey !== "object") {
        setDeveloperFeedback("No se pudo leer la clave primaria de la fila.", "error");
        return;
      }

      openDeveloperEditModal(primaryKey);
      return;
    }

    const developerDeleteRowBtn = e.target.closest('[data-action="developer-delete-row"]');
    if (developerDeleteRowBtn) {
      const tableName = getDeveloperSelectedTable();
      if (!tableName) {
        setDeveloperFeedback("Debes seleccionar una tabla.", "error");
        return;
      }

      const primaryKey = decodeDeveloperPayload(developerDeleteRowBtn.dataset.primaryKey ?? "");
      if (!primaryKey || typeof primaryKey !== "object") {
        setDeveloperFeedback("No se pudo leer la clave primaria de la fila.", "error");
        return;
      }

      const primarySummary = Object.entries(primaryKey)
        .map(([key, value]) => `${key}=${value}`)
        .join(", ");
      const confirmed = window.confirm(`Se eliminará la fila ${primarySummary || "seleccionada"} de ${tableName}. ¿Deseas continuar?`);
      if (!confirmed) return;

      const originalLabel = developerDeleteRowBtn.textContent;
      developerDeleteRowBtn.disabled = true;
      developerDeleteRowBtn.textContent = "Eliminando...";
      setDeveloperFeedback("", "error");

      try {
        const result = await requestDeveloperAction("eliminar-fila-tabla", {
          table: tableName,
          primary_key: JSON.stringify(primaryKey)
        });

        if (result.logout) {
          window.location.href = result.redirect || "?route=login";
          return;
        }

        if (result.data) {
          renderDeveloperPanel(result.data);
        }

        setDeveloperFeedback(result.message || "Fila eliminada correctamente.", "success");
      } catch (error) {
        setDeveloperFeedback(error.message, "error");
      } finally {
        developerDeleteRowBtn.disabled = false;
        developerDeleteRowBtn.textContent = originalLabel;
      }
      return;
    }

    const newVentaBtn = e.target.closest('[data-action="nueva-venta"]');
    if (newVentaBtn) {
      resetVentaCreateForm();
      openVentaCreateModal();
      return;
    }

    const ventaActionModalBtn = e.target.closest('#venta-actions-modal [data-action]');
    if (ventaActionModalBtn) {
      closeVentaActionsModal();
    }

    const ventaRowTrigger = e.target.closest('tr[data-venta-row="1"]');
    if (
      ventaRowTrigger &&
      !e.target.closest(".btn") &&
      !e.target.closest("a, input, select, textarea, label, button")
    ) {
      const saldoLabel = String(ventaRowTrigger.dataset.saldoLabel ?? "$0.00");
      const saldoValue = Number.parseFloat(saldoLabel.replace(/[^0-9.-]+/g, "")) || 0;

      fillVentaActionsModal({
        id: Number(ventaRowTrigger.dataset.ventaId ?? 0),
        idPedido: Number(ventaRowTrigger.dataset.pedidoId ?? 0),
        cliente: ventaRowTrigger.dataset.cliente ?? "",
        totalLabel: ventaRowTrigger.dataset.totalLabel ?? "$0.00",
        saldoLabel,
        saldo: saldoValue,
        estadoPago: ventaRowTrigger.dataset.estadoPago ?? "-",
        metodo: ventaRowTrigger.dataset.metodo ?? "-",
        fecha: ventaRowTrigger.dataset.fecha ?? "-",
        estadoPedido: ventaRowTrigger.dataset.estadoPedido ?? "-",
        detailButton: ventaRowTrigger.querySelector('[data-action="ver-venta-detalle"]'),
        editButton: ventaRowTrigger.querySelector('[data-action="editar-venta"]'),
        abonoButton: ventaRowTrigger.querySelector('[data-action="registrar-abono-venta"]'),
        deleteButton: ventaRowTrigger.querySelector('[data-action="eliminar-venta"]')
      });
      openVentaActionsModal();
      return;
    }

    const editVentaBtn = e.target.closest('[data-action="editar-venta"]');
    if (editVentaBtn) {
      fillVentaEditForm({
        id: editVentaBtn.dataset.id ?? "",
        total: editVentaBtn.dataset.total ?? "",
        metodoPago: editVentaBtn.dataset.metodo ?? "efectivo"
      });
      openVentaEditModal();
      return;
    }

    const viewVentaDetalleBtn = e.target.closest('[data-action="ver-venta-detalle"]');
    if (viewVentaDetalleBtn) {
      const idVenta = Number(viewVentaDetalleBtn.dataset.id ?? 0);
      if (idVenta <= 0) return;

      openVentaDetalleModal();
      setVentaDetalleFeedback("Cargando detalle de la venta...", "success");

      try {
        const data = await requestVentaDetail(idVenta);
        fillVentaDetalleModal(data.venta || {}, data.abonos || []);
      } catch (error) {
        setVentaDetalleFeedback(error.message, "error");
      }
      return;
    }

    const registrarAbonoBtn = e.target.closest('[data-action="registrar-abono-venta"]');
    if (registrarAbonoBtn) {
      const payload = {
        idVenta: Number(registrarAbonoBtn.dataset.id ?? 0),
        idPedido: Number(registrarAbonoBtn.dataset.pedido ?? 0),
        cliente: registrarAbonoBtn.dataset.cliente ?? "",
        total: Number(registrarAbonoBtn.dataset.total ?? 0),
        abonado: Number(registrarAbonoBtn.dataset.abonado ?? 0),
        saldo: Number(registrarAbonoBtn.dataset.saldo ?? 0)
      };

      if (payload.idVenta <= 0) return;

      fillVentaAbonoForm(payload);
      openVentaAbonoModal();
      return;
    }

    const deleteVentaBtn = e.target.closest('[data-action="eliminar-venta"]');
    if (deleteVentaBtn) {
      const idVenta = deleteVentaBtn.dataset.id;
      const confirmed = window.confirm("¿Seguro que deseas eliminar esta venta?");
      if (!confirmed) return;

      try {
        await requestVentaDelete(idVenta);
        await loadRouteContent("ventas");
      } catch (error) {
        alert(error.message);
      }
      return;
    }

    const newCategoriaBtn = e.target.closest('[data-action="nueva-categoria"]');
    if (newCategoriaBtn) {
      resetCategoriaCreateForm();
      openCategoriaCreateModal();
      return;
    }

    const editCategoriaBtn = e.target.closest('[data-action="editar-categoria"]');
    if (editCategoriaBtn) {
      fillCategoriaEditForm({
        id: editCategoriaBtn.dataset.id ?? "",
        tipoCategoria: editCategoriaBtn.dataset.tipo ?? "",
        estado: editCategoriaBtn.dataset.estado ?? "activo"
      });
      openCategoriaEditModal();
      return;
    }

    const deleteCategoriaBtn = e.target.closest('[data-action="eliminar-categoria"]');
    if (deleteCategoriaBtn) {
      const idCategoria = deleteCategoriaBtn.dataset.id;
      const nombreCategoria = deleteCategoriaBtn.dataset.tipo || "esta categoría";
      const confirmed = window.confirm(`¿Seguro que deseas eliminar ${nombreCategoria}?`);
      if (!confirmed) return;

      try {
        await requestCategoriaDelete(idCategoria);
        await loadRouteContent("inventario");
      } catch (error) {
        alert(error.message);
      }
      return;
    }

    const newProductoBtn = e.target.closest('[data-action="nuevo-producto"]');
    if (newProductoBtn) {
      resetProductoCreateForm();
      openProductoCreateModal();
      return;
    }

    const editProductoBtn = e.target.closest('[data-action="editar-producto"]');
    if (editProductoBtn) {
      fillProductoEditForm({
        id: editProductoBtn.dataset.id ?? "",
        idCategoria: editProductoBtn.dataset.idCategoria ?? "",
        nombreProducto: editProductoBtn.dataset.nombre ?? "",
        descripcion: editProductoBtn.dataset.descripcion ?? "",
        precioBase: editProductoBtn.dataset.precio ?? "",
        stockActual: editProductoBtn.dataset.stockActual ?? "0",
        stockMinimo: editProductoBtn.dataset.stockMinimo ?? "5",
        estado: editProductoBtn.dataset.estado ?? "activo"
      });
      openProductoEditModal();
      return;
    }

    const deleteProductoBtn = e.target.closest('[data-action="eliminar-producto"]');
    if (deleteProductoBtn) {
      const idProducto = deleteProductoBtn.dataset.id;
      const nombreProducto = deleteProductoBtn.dataset.nombre || "este producto";
      const confirmed = window.confirm(`¿Seguro que deseas eliminar ${nombreProducto}?`);
      if (!confirmed) return;

      try {
        await requestProductoDelete(idProducto);
        await loadRouteContent("inventario");
      } catch (error) {
        alert(error.message);
      }
      return;
    }

    const newPerfilUserBtn = e.target.closest('[data-action="nuevo-usuario-perfil"]');
    if (newPerfilUserBtn) {
      resetPerfilCreateForm();
      openPerfilCreateModal();
      return;
    }

    const editPerfilUserBtn = e.target.closest('[data-action="editar-usuario-perfil"]');
    if (editPerfilUserBtn) {
      fillPerfilEditForm({
        id: editPerfilUserBtn.dataset.id ?? "",
        usuario: editPerfilUserBtn.dataset.usuario ?? "",
        correo: editPerfilUserBtn.dataset.correo ?? "",
        idRol: editPerfilUserBtn.dataset.idRol ?? "",
        estado: editPerfilUserBtn.dataset.estado ?? "activo"
      });
      openPerfilEditModal();
      return;
    }

    const deletePerfilUserBtn = e.target.closest('[data-action="eliminar-usuario-perfil"]');
    if (deletePerfilUserBtn) {
      const idUsuario = Number(deletePerfilUserBtn.dataset.id ?? 0);
      const nombreUsuario = deletePerfilUserBtn.dataset.usuario || "este usuario";
      const confirmed = window.confirm(`¿Seguro que deseas desactivar a ${nombreUsuario}?`);
      if (!confirmed) return;

      try {
        const data = await requestPerfilDelete(idUsuario);
        if (data.logout) {
          window.location.href = "?route=login";
          return;
        }
        await loadRouteContent("perfil");
      } catch (error) {
        alert(error.message);
      }
      return;
    }

    const deleteMyProfileBtn = e.target.closest('[data-action="eliminar-mi-perfil"]');
    if (deleteMyProfileBtn) {
      const idUsuario = Number(deleteMyProfileBtn.dataset.id ?? 0);
      const confirmed = window.confirm("¿Seguro que deseas desactivar tu perfil? Esta acción cerrará tu sesión.");
      if (!confirmed) return;

      try {
        const data = await requestPerfilDelete(idUsuario);
        if (data.logout) {
          window.location.href = "?route=login";
          return;
        }
        await loadRouteContent("perfil");
      } catch (error) {
        alert(error.message);
      }
      return;
    }

    const newClienteBtn = e.target.closest('[data-action="nuevo-cliente"]');
    if (newClienteBtn) {
      resetClienteCreateForm();
      openClienteCreateModal();
      return;
    }

    const editClienteBtn = e.target.closest('[data-action="editar-cliente"]');
    if (editClienteBtn) {
      const payload = {
        id: editClienteBtn.dataset.id ?? "",
        nombre: editClienteBtn.dataset.nombre ?? "",
        apellido: editClienteBtn.dataset.apellido ?? "",
        cedula: editClienteBtn.dataset.cedula ?? "",
        telefono: editClienteBtn.dataset.telefono ?? "",
        direccion: editClienteBtn.dataset.direccion ?? "",
        empresa: editClienteBtn.dataset.empresa ?? ""
      };

      fillClienteEditForm(payload);
      openClienteEditModal();
      return;
    }

    const deleteClienteBtn = e.target.closest('[data-action="eliminar-cliente"]');
    if (deleteClienteBtn) {
      const idCliente = deleteClienteBtn.dataset.id;
      const nombreCliente = deleteClienteBtn.dataset.nombre || "este cliente";
      const confirmed = window.confirm(`¿Seguro que deseas eliminar a ${nombreCliente}?`);
      if (!confirmed) return;

      try {
        await requestClienteDelete(idCliente);
        await loadRouteContent("clientes");
      } catch (error) {
        alert(error.message);
      }
      return;
    }

    const newPedidoBtn = e.target.closest('[data-action="nuevo-pedido"]');
    if (newPedidoBtn) {
      resetPedidoCreateForm();
      openPedidoCreateModal();
      return;
    }

    const addItemBtn = e.target.closest('[data-action="agregar-item-pedido"]');
    if (addItemBtn) {
      const config = getPedidoFormConfigFromElement(addItemBtn);
      addPedidoItemRow(config.containerSelector, config.totalSelector);
      return;
    }

    const removeItemBtn = e.target.closest('[data-action="quitar-item-pedido"]');
    if (removeItemBtn) {
      removePedidoItemRow(removeItemBtn);
      return;
    }

    const addMedidaBtn = e.target.closest('[data-action="agregar-medida-item"]');
    if (addMedidaBtn) {
      const itemRow = addMedidaBtn.closest(".pedido-item-row");
      addPedidoMedidaRowToItem(itemRow);
      return;
    }

    const removeMedidaBtn = e.target.closest('[data-action="quitar-medida-item"]');
    if (removeMedidaBtn) {
      removePedidoMedidaRow(removeMedidaBtn);
      return;
    }

    const pedidoActionModalBtn = e.target.closest('#pedido-actions-modal [data-action]');
    if (pedidoActionModalBtn) {
      closePedidoActionsModal();
    }

    const pedidoRowTrigger = e.target.closest('tr[data-pedido-row="1"]');
    if (
      pedidoRowTrigger &&
      !e.target.closest(".btn") &&
      !e.target.closest("a, input, select, textarea, label, button")
    ) {
      fillPedidoActionsModal({
        id: Number(pedidoRowTrigger.dataset.id ?? 0),
        cliente: pedidoRowTrigger.dataset.cliente ?? "",
        cedula: pedidoRowTrigger.dataset.cedula ?? "",
        totalLabel: pedidoRowTrigger.dataset.totalLabel ?? "$0.00",
        fecha: pedidoRowTrigger.dataset.fecha ?? "-",
        estado: pedidoRowTrigger.dataset.estado ?? "-",
        idVenta: Number(pedidoRowTrigger.dataset.ventaId ?? 0),
        viewButton: pedidoRowTrigger.querySelector('[data-action="ver-pedido"]'),
        editButton: pedidoRowTrigger.querySelector('[data-action="editar-pedido"]'),
        deleteButton: pedidoRowTrigger.querySelector('[data-action="eliminar-pedido"]')
      });
      openPedidoActionsModal();
      return;
    }

    const viewBtn = e.target.closest('[data-action="ver-pedido"]');
    if (viewBtn) {
      const idPedido = viewBtn.dataset.id;
      openPedidoModal();
      const itemsContainer = document.querySelector("#pedido-detalle-items");
      if (itemsContainer) {
        itemsContainer.innerHTML = `
          <tr>
            <td colspan="5" style="text-align:center;">Cargando...</td>
          </tr>
        `;
      }

      try {
        await loadPedidoDetail(idPedido);
      } catch (error) {
        if (itemsContainer) {
          itemsContainer.innerHTML = `
            <tr>
              <td colspan="5" style="text-align:center;">${escapeHtml(error.message)}</td>
            </tr>
          `;
        }
      }
      return;
    }

    const editBtn = e.target.closest('[data-action="editar-pedido"]');
    if (editBtn) {
      const idPedido = editBtn.dataset.id;
      const submitBtn = document.querySelector("#pedido-edit-submit");
      const idInput = document.querySelector("#pedido-edit-id");

      resetPedidoEditForm();

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Cargando...";
      }
      if (idInput) {
        idInput.value = String(idPedido ?? "");
      }
      setPedidoEditFeedback("", "error");
      const feedback = document.querySelector("#pedido-edit-feedback");
      if (feedback) {
        feedback.hidden = true;
      }

      openPedidoEditModal();

      try {
        const data = await requestPedidoDetail(idPedido);
        fillPedidoEditForm(data);
      } catch (error) {
        setPedidoEditFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = "Error";
        }
      }
      return;
    }

    const deletePedidoBtn = e.target.closest('[data-action="eliminar-pedido"]');
    if (deletePedidoBtn) {
      if (deletePedidoBtn.disabled) {
        return;
      }

      const idPedido = deletePedidoBtn.dataset.id;
      const confirmed = window.confirm("¿Seguro que deseas eliminar este pedido?");
      if (!confirmed) return;

      try {
        await requestPedidoDelete(idPedido);
        closePedidoActionsModal();
        closePedidoEditModal();
        closePedidoModal();
        await loadRouteContent("pedidos");
      } catch (error) {
        alert(error.message);
      }
      return;
    }

    const historialActionModalBtn = e.target.closest('#historial-actions-modal [data-action]');
    if (historialActionModalBtn) {
      closeHistorialActionsModal();
    }

    const historialRowTrigger = e.target.closest('tr[data-historial-row="1"]');
    if (
      historialRowTrigger &&
      !e.target.closest(".btn") &&
      !e.target.closest("a, input, select, textarea, label, button")
    ) {
      fillHistorialActionsModal({
        id: Number(historialRowTrigger.dataset.historialId ?? 0),
        idVenta: Number(historialRowTrigger.dataset.ventaId ?? 0),
        idPedido: Number(historialRowTrigger.dataset.pedidoId ?? 0),
        cliente: historialRowTrigger.dataset.cliente ?? "",
        totalLabel: historialRowTrigger.dataset.totalLabel ?? "$0.00",
        estadoPago: historialRowTrigger.dataset.estadoPago ?? "-",
        metodo: historialRowTrigger.dataset.metodo ?? "-",
        fecha: historialRowTrigger.dataset.fecha ?? "-",
        estadoHistorial: historialRowTrigger.dataset.estadoHistorial ?? "-",
        canAnular: Boolean(historialRowTrigger.querySelector('[data-action="anular-historial"]')),
        viewButton: historialRowTrigger.querySelector('[data-action="ver-historial"]'),
        printButton: historialRowTrigger.querySelector('[data-action="imprimir-historial"]'),
        anularButton: historialRowTrigger.querySelector('[data-action="anular-historial"]')
      });
      openHistorialActionsModal();
      return;
    }

    const viewHistorialBtn = e.target.closest('[data-action="ver-historial"]');
    if (viewHistorialBtn) {
      const idHistorial = viewHistorialBtn.dataset.id;
      openHistorialModal();
      setHistorialFeedback("Cargando detalle...", "success");

      try {
        await loadHistorialDetail(idHistorial);
        setHistorialFeedback("", "success");
      } catch (error) {
        setHistorialFeedback(error.message, "error");
      }
      return;
    }

    const printHistorialBtn = e.target.closest('[data-action="imprimir-historial"]');
    if (printHistorialBtn) {
      const idHistorial = printHistorialBtn.dataset.id;

      try {
        const data = await requestHistorialDetail(idHistorial);
        printHistorialRegistro(data.registro || {}, data.detalle || [], data.resumen || {});
      } catch (error) {
        alert(error.message);
      }
      return;
    }

    const anularHistorialBtn = e.target.closest('[data-action="anular-historial"]');
    if (anularHistorialBtn) {
      const idHistorial = anularHistorialBtn.dataset.id;
      const confirmed = window.confirm("¿Seguro que deseas anular este registro del historial?");
      if (!confirmed) return;

      try {
        const data = await anularHistorialRegistro(idHistorial);
        refreshHistorialRowAsAnulado(data.id ?? idHistorial);
        if (isHistorialModalVisible()) {
          setHistorialFeedback(data.message || "Registro anulado.", "success");
        }
      } catch (error) {
        if (isHistorialModalVisible()) {
          setHistorialFeedback(error.message, "error");
        } else {
          alert(error.message);
        }
      }
      return;
    }

    const closeBtnModal = e.target.closest('[data-close]');
    if (closeBtnModal?.dataset?.close === "pedido-actions-modal") {
      closePedidoActionsModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "pedido-modal") {
      closePedidoModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "pedido-edit-modal") {
      closePedidoEditModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "pedido-create-modal") {
      closePedidoCreateModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "venta-create-modal") {
      closeVentaCreateModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "venta-edit-modal") {
      closeVentaEditModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "venta-abono-modal") {
      closeVentaAbonoModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "venta-detalle-modal") {
      closeVentaDetalleModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "venta-actions-modal") {
      closeVentaActionsModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "categoria-create-modal") {
      closeCategoriaCreateModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "categoria-edit-modal") {
      closeCategoriaEditModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "producto-create-modal") {
      closeProductoCreateModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "producto-edit-modal") {
      closeProductoEditModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "perfil-create-modal") {
      closePerfilCreateModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "perfil-edit-modal") {
      closePerfilEditModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "developer-row-edit-modal") {
      closeDeveloperEditModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "cliente-create-modal") {
      closeClienteCreateModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "cliente-edit-modal") {
      closeClienteEditModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "historial-actions-modal") {
      closeHistorialActionsModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "historial-modal") {
      closeHistorialModal();
      return;
    }
  });

  content.addEventListener("change", (e) => {
    if (e.target.matches("#developer-table-select")) {
      const tableName = String(e.target.value ?? "").trim();
      if (!tableName) return;

      setDeveloperFeedback("", "error");
      requestDeveloperData(tableName)
        .then((data) => {
          renderDeveloperPanel(data);
        })
        .catch((error) => {
          setDeveloperFeedback(error.message, "error");
        });
      return;
    }

    if (e.target.matches("#home-ingresos-periodo")) {
      homeDashboardState.ingresosPeriodo = normalizeHomeIngresosPeriodo(e.target.value);
      refreshHomeDashboardData().catch((error) => console.error(error));
      return;
    }

    if (e.target.matches("#venta-create-pedido")) {
      updateVentaCreateTotalFromPedido();
      return;
    }

    if (e.target.closest("#pedido-create-form")) {
      if (
        e.target.matches(".pedido-item-producto") ||
        e.target.matches(".pedido-item-cantidad")
      ) {
        updatePedidoCreateTotal();
      }
      return;
    }

    if (e.target.closest("#pedido-edit-form")) {
      if (
        e.target.matches(".pedido-item-producto") ||
        e.target.matches(".pedido-item-cantidad")
      ) {
        updatePedidoEditTotal();
      }
    }
  });

  content.addEventListener("input", (e) => {
    if (e.target.closest("#pedido-create-form")) {
      if (e.target.matches(".pedido-item-cantidad")) {
        updatePedidoCreateTotal();
      }
      return;
    }

    if (e.target.closest("#pedido-edit-form")) {
      if (e.target.matches(".pedido-item-cantidad")) {
        updatePedidoEditTotal();
      }
    }
  });

  content.addEventListener("submit", async (e) => {
    const developerEditForm = e.target.closest("#developer-row-edit-form");
    if (developerEditForm) {
      e.preventDefault();

      const tableName = getDeveloperSelectedTable();
      if (!tableName) {
        setDeveloperEditFeedback("Debes seleccionar una tabla.", "error");
        return;
      }

      if (!developerPanelState.editPrimaryKey || typeof developerPanelState.editPrimaryKey !== "object") {
        setDeveloperEditFeedback("No se identificó la fila a editar.", "error");
        return;
      }

      const payload = getDeveloperEditPayload();
      const submitBtn = document.querySelector("#developer-row-edit-submit");
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setDeveloperEditFeedback("", "error");

      try {
        const result = await requestDeveloperAction("actualizar-fila-tabla", {
          table: tableName,
          primary_key: JSON.stringify(developerPanelState.editPrimaryKey),
          fields: JSON.stringify(payload)
        });

        closeDeveloperEditModal();

        if (result.logout) {
          window.location.href = result.redirect || "?route=login";
          return;
        }

        if (result.data) {
          renderDeveloperPanel(result.data);
        }

        setDeveloperFeedback(result.message || "Fila actualizada correctamente.", "success");
      } catch (error) {
        setDeveloperEditFeedback(error.message, "error");
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar cambios";
        }
      }
      return;
    }

    const perfilUpdateForm = e.target.closest("#perfil-update-form");
    if (perfilUpdateForm) {
      e.preventDefault();

      const payload = getPerfilUpdatePayload();
      const submitBtn = document.querySelector("#perfil-update-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setPerfilUpdateFeedback("", "error");

      if (payload.id <= 0) {
        setPerfilUpdateFeedback("ID de perfil inválido.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      if (!payload.usuario || !payload.correo) {
        setPerfilUpdateFeedback("Usuario y correo son obligatorios.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      try {
        await requestPerfilUpdate({
          id: payload.id,
          usuario: payload.usuario,
          correo: payload.correo,
          contrasena: payload.contrasena,
          idRol: 0,
          estado: ""
        });
        await loadRouteContent("perfil");
      } catch (error) {
        setPerfilUpdateFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
      }
      return;
    }

    const perfilCreateForm = e.target.closest("#perfil-create-form");
    if (perfilCreateForm) {
      e.preventDefault();

      const payload = getPerfilCreatePayload();
      const submitBtn = document.querySelector("#perfil-create-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setPerfilCreateFeedback("", "error");

      if (!payload.usuario || !payload.correo || !payload.contrasena) {
        setPerfilCreateFeedback("Usuario, correo y contraseña son obligatorios.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Usuario";
        }
        return;
      }

      if (payload.idRol <= 0) {
        setPerfilCreateFeedback("Debes seleccionar un rol válido.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Usuario";
        }
        return;
      }

      try {
        await requestPerfilCreate(payload);
        closePerfilCreateModal();
        await loadRouteContent("perfil");
      } catch (error) {
        setPerfilCreateFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Usuario";
        }
      }
      return;
    }

    const perfilEditForm = e.target.closest("#perfil-edit-form");
    if (perfilEditForm) {
      e.preventDefault();

      const payload = getPerfilEditPayload();
      const submitBtn = document.querySelector("#perfil-edit-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setPerfilEditFeedback("", "error");

      if (payload.id <= 0) {
        setPerfilEditFeedback("ID de usuario inválido.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      if (!payload.usuario || !payload.correo) {
        setPerfilEditFeedback("Usuario y correo son obligatorios.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      if (payload.idRol <= 0) {
        setPerfilEditFeedback("Debes seleccionar un rol válido.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      try {
        await requestPerfilUpdate(payload);
        closePerfilEditModal();
        await loadRouteContent("perfil");
      } catch (error) {
        setPerfilEditFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
      }
      return;
    }

    const ventaEditForm = e.target.closest("#venta-edit-form");
    if (ventaEditForm) {
      e.preventDefault();

      const payload = getVentaEditPayload();
      const submitBtn = document.querySelector("#venta-edit-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setVentaEditFeedback("", "error");

      if (payload.id <= 0) {
        setVentaEditFeedback("ID de venta inválido.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      if (!Number.isFinite(payload.total) || payload.total <= 0) {
        setVentaEditFeedback("El total debe ser mayor a 0.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      try {
        await requestVentaUpdate(payload);
        closeVentaEditModal();
        await loadRouteContent("ventas");
      } catch (error) {
        setVentaEditFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
      }
      return;
    }

    const ventaCreateForm = e.target.closest("#venta-create-form");
    if (ventaCreateForm) {
      e.preventDefault();

      const payload = getVentaCreatePayload();
      const submitBtn = document.querySelector("#venta-create-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setVentaCreateFeedback("", "error");

      if (payload.idPedido <= 0) {
        setVentaCreateFeedback("Debes seleccionar un pedido.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Venta";
        }
        return;
      }

      if (!Number.isFinite(payload.total) || payload.total <= 0) {
        setVentaCreateFeedback("El total debe ser mayor a 0.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Venta";
        }
        return;
      }

      if (payload.abonoInicialRaw !== "") {
        if (!Number.isFinite(payload.abonoInicial) || payload.abonoInicial < 0) {
          setVentaCreateFeedback("El abono inicial debe ser un valor válido (0 o mayor).", "error");
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Guardar Venta";
          }
          return;
        }

        if (payload.abonoInicial > payload.total) {
          setVentaCreateFeedback("El abono inicial no puede superar el total.", "error");
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Guardar Venta";
          }
          return;
        }
      }

      try {
        await requestVentaCreate(payload);
        closeVentaCreateModal();
        await loadRouteContent("ventas");
      } catch (error) {
        setVentaCreateFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Venta";
        }
      }
      return;
    }

    const ventaAbonoForm = e.target.closest("#venta-abono-form");
    if (ventaAbonoForm) {
      e.preventDefault();

      const payload = getVentaAbonoPayload();
      const submitBtn = document.querySelector("#venta-abono-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setVentaAbonoFeedback("", "error");

      if (payload.idVenta <= 0) {
        setVentaAbonoFeedback("Venta inválida para registrar el abono.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Abono";
        }
        return;
      }

      if (!Number.isFinite(payload.monto) || payload.monto <= 0) {
        setVentaAbonoFeedback("El monto del abono debe ser mayor a 0.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Abono";
        }
        return;
      }

      try {
        const data = await requestVentaAbonoCreate(payload);
        setVentaAbonoFeedback(data.message || "Abono registrado.", "success");
        closeVentaAbonoModal();
        await loadRouteContent("ventas");
      } catch (error) {
        setVentaAbonoFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Abono";
        }
      }
      return;
    }

    const categoriaEditForm = e.target.closest("#categoria-edit-form");
    if (categoriaEditForm) {
      e.preventDefault();

      const payload = getCategoriaEditPayload();
      const submitBtn = document.querySelector("#categoria-edit-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setCategoriaEditFeedback("", "error");

      if (payload.id <= 0) {
        setCategoriaEditFeedback("ID de categoría inválido.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      if (!payload.tipoCategoria) {
        setCategoriaEditFeedback("El nombre de la categoría es obligatorio.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      try {
        await requestCategoriaUpdate(payload);
        closeCategoriaEditModal();
        await loadRouteContent("inventario");
      } catch (error) {
        setCategoriaEditFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
      }
      return;
    }

    const categoriaCreateForm = e.target.closest("#categoria-create-form");
    if (categoriaCreateForm) {
      e.preventDefault();

      const payload = getCategoriaCreatePayload();
      const submitBtn = document.querySelector("#categoria-create-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setCategoriaCreateFeedback("", "error");

      if (!payload.tipoCategoria) {
        setCategoriaCreateFeedback("El nombre de la categoría es obligatorio.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Categoría";
        }
        return;
      }

      try {
        await requestCategoriaCreate(payload);
        closeCategoriaCreateModal();
        await loadRouteContent("inventario");
      } catch (error) {
        setCategoriaCreateFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Categoría";
        }
      }
      return;
    }

    const productoEditForm = e.target.closest("#producto-edit-form");
    if (productoEditForm) {
      e.preventDefault();

      const payload = getProductoEditPayload();
      const submitBtn = document.querySelector("#producto-edit-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setProductoEditFeedback("", "error");

      if (payload.id <= 0) {
        setProductoEditFeedback("ID de producto inválido.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      if (!payload.nombreProducto) {
        setProductoEditFeedback("El nombre del producto es obligatorio.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      if (!Number.isFinite(payload.precioBase) || payload.precioBase <= 0) {
        setProductoEditFeedback("El precio base debe ser mayor a 0.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      if (!Number.isInteger(payload.stockActual) || payload.stockActual < 0) {
        setProductoEditFeedback("El stock actual debe ser un entero igual o mayor a 0.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      if (!Number.isInteger(payload.stockMinimo) || payload.stockMinimo < 0) {
        setProductoEditFeedback("El stock mínimo debe ser un entero igual o mayor a 0.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      try {
        await requestProductoUpdate(payload);
        closeProductoEditModal();
        await loadRouteContent("inventario");
      } catch (error) {
        setProductoEditFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
      }
      return;
    }

    const productoCreateForm = e.target.closest("#producto-create-form");
    if (productoCreateForm) {
      e.preventDefault();

      const payload = getProductoCreatePayload();
      const submitBtn = document.querySelector("#producto-create-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setProductoCreateFeedback("", "error");

      if (!payload.nombreProducto) {
        setProductoCreateFeedback("El nombre del producto es obligatorio.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Producto";
        }
        return;
      }

      if (!Number.isFinite(payload.precioBase) || payload.precioBase <= 0) {
        setProductoCreateFeedback("El precio base debe ser mayor a 0.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Producto";
        }
        return;
      }

      if (!Number.isInteger(payload.stockActual) || payload.stockActual < 0) {
        setProductoCreateFeedback("El stock actual debe ser un entero igual o mayor a 0.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Producto";
        }
        return;
      }

      if (!Number.isInteger(payload.stockMinimo) || payload.stockMinimo < 0) {
        setProductoCreateFeedback("El stock mínimo debe ser un entero igual o mayor a 0.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Producto";
        }
        return;
      }

      try {
        await requestProductoCreate(payload);
        closeProductoCreateModal();
        await loadRouteContent("inventario");
      } catch (error) {
        setProductoCreateFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Producto";
        }
      }
      return;
    }

    const clienteEditForm = e.target.closest("#cliente-edit-form");
    if (clienteEditForm) {
      e.preventDefault();

      const payload = getClienteEditPayload();
      const submitBtn = document.querySelector("#cliente-edit-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setClienteEditFeedback("", "error");

      if (payload.id <= 0) {
        setClienteEditFeedback("ID de cliente inválido.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      if (!payload.nombre || !payload.apellido) {
        setClienteEditFeedback("Nombre y apellido son obligatorios.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
        return;
      }

      try {
        await requestClienteUpdate(payload);
        closeClienteEditModal();
        await loadRouteContent("clientes");
      } catch (error) {
        setClienteEditFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cambios";
        }
      }
      return;
    }

    const clienteForm = e.target.closest("#cliente-create-form");
    if (clienteForm) {
      e.preventDefault();

      const payload = getClienteCreatePayload();
      const submitBtn = document.querySelector("#cliente-create-submit");

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Guardando...";
      }

      setClienteCreateFeedback("", "error");

      if (!payload.nombre || !payload.apellido) {
        setClienteCreateFeedback("Nombre y apellido son obligatorios.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cliente";
        }
        return;
      }

      try {
        await requestClienteCreate(payload);
        closeClienteCreateModal();
        await loadRouteContent("clientes");
      } catch (error) {
        setClienteCreateFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Guardar Cliente";
        }
      }
      return;
    }

    const createForm = e.target.closest("#pedido-create-form");
    if (createForm) {
      e.preventDefault();

      const submitBtn = document.querySelector("#pedido-create-submit");
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Creando...";
      }

      setPedidoCreateFeedback("", "error");
      const payload = getPedidoCreatePayload();

      if (payload.idCliente <= 0) {
        setPedidoCreateFeedback("Debes seleccionar un cliente.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Crear Pedido";
        }
        return;
      }

      if (payload.items.length === 0) {
        setPedidoCreateFeedback("Debes agregar al menos un producto válido.", "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Crear Pedido";
        }
        return;
      }

      const validationError = getPedidoCreateValidationError(payload);
      if (validationError) {
        setPedidoCreateFeedback(validationError, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Crear Pedido";
        }
        return;
      }

      try {
        await requestPedidoCreate(payload);
        closePedidoCreateModal();
        await loadRouteContent("pedidos");
      } catch (error) {
        setPedidoCreateFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Crear Pedido";
        }
      }
      return;
    }

    const form = e.target.closest("#pedido-edit-form");
    if (!form) return;

    e.preventDefault();

    const submitBtn = document.querySelector("#pedido-edit-submit");
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = "Guardando...";
    }

    setPedidoEditFeedback("", "error");

    const payload = getPedidoEditPayload();

    if (payload.id <= 0) {
      setPedidoEditFeedback("ID de pedido inválido.", "error");
      if (submitBtn) {
        submitBtn.textContent = "Guardar cambios";
        submitBtn.disabled = false;
      }
      return;
    }

    if (payload.idCliente <= 0) {
      setPedidoEditFeedback("Debes seleccionar un cliente.", "error");
      if (submitBtn) {
        submitBtn.textContent = "Guardar cambios";
        submitBtn.disabled = false;
      }
      return;
    }

    if (payload.items.length === 0) {
      setPedidoEditFeedback("Debes agregar al menos un producto válido.", "error");
      if (submitBtn) {
        submitBtn.textContent = "Guardar cambios";
        submitBtn.disabled = false;
      }
      return;
    }

    const validationError = getPedidoValidationError(payload, "actualizar");
    if (validationError) {
      setPedidoEditFeedback(validationError, "error");
      if (submitBtn) {
        submitBtn.textContent = "Guardar cambios";
        submitBtn.disabled = false;
      }
      return;
    }

    try {
      await requestPedidoUpdate(payload);
      closePedidoActionsModal();
      closePedidoEditModal();
      await loadRouteContent("pedidos");
    } catch (error) {
      setPedidoEditFeedback(error.message, "error");
      if (submitBtn) {
        submitBtn.textContent = "Reintentar";
        submitBtn.disabled = false;
      }
    }
  });
}

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closePedidoActionsModal();
    closePedidoModal();
    closePedidoEditModal();
    closePedidoCreateModal();
    closeVentaCreateModal();
    closeVentaEditModal();
    closeVentaAbonoModal();
    closeVentaDetalleModal();
    closeVentaActionsModal();
    closeCategoriaCreateModal();
    closeCategoriaEditModal();
    closeProductoCreateModal();
    closeProductoEditModal();
    closePerfilCreateModal();
    closePerfilEditModal();
    closeDeveloperEditModal();
    closeClienteCreateModal();
    closeClienteEditModal();
    closeHistorialActionsModal();
    closeHistorialModal();
  }
});

// Logout action
if (logoutBtn) {
  const goLogout = () => {
    window.location.href = "?route=logout";
  };

  logoutBtn.addEventListener("click", goLogout);
}
