

// Get the sidebar, close button, and search button elements
let sidebar = document.querySelector(".sidebar");
let closeBtn = document.querySelector("#btn");
let searchBtn = document.querySelector(".bx-search");
let logoutBtn = document.querySelector("#log_out");
const content = document.querySelector("#content");
const homeDashboardState = {
  intervalId: null,
  chartVentasMes: null,
  chartPedidos: null,
  isFetching: false,
  ingresosPeriodo: "mes"
};
const TABLE_PAGE_SIZE = 5;
let tablePaginationSequence = 0;

// Event listener for the menu button to toggle the sidebar open/close
closeBtn.addEventListener("click", () => {
  sidebar.classList.toggle("open"); // Toggle the sidebar's open state
  menuBtnChange(); // Call function to change button icon
});

// Event listener for the search button to open the sidebar
searchBtn.addEventListener("click", () => {
  sidebar.classList.toggle("open");
  menuBtnChange(); // Call function to change button icon
});

// Function to change the menu button icon
function menuBtnChange() {
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
  await bootRouteModules(page);
}

async function bootRouteModules(page) {
  const isHomeRoute = page === "home" || Boolean(content?.querySelector('[data-home-dashboard="1"]'));
  if (isHomeRoute) {
    await initHomeDashboardRealtime();
    initTablePagination(content);
    return;
  }

  destroyHomeDashboardRealtime();
  initTablePagination(content);
  await initDeveloperPanel();
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

function updateDeveloperDbStatus(statusValue) {
  const node = document.querySelector("#developer-db-status");
  if (!node) return;

  const status = String(statusValue ?? "").toLowerCase().trim();
  const isOk = status === "ok";

  node.classList.remove("ok", "error");
  node.classList.add(isOk ? "ok" : "error");
  node.textContent = isOk ? "Conectada" : "Error";
}

function renderDeveloperPanel(data) {
  const payload = data && typeof data === "object" ? data : {};
  const info = payload.info && typeof payload.info === "object" ? payload.info : {};
  const resumen = payload.resumen && typeof payload.resumen === "object" ? payload.resumen : {};
  const integridad = payload.integridad && typeof payload.integridad === "object" ? payload.integridad : {};

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
}

async function requestDeveloperData() {
  const response = await fetch("?route=developer-data");
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

async function requestDeveloperAction(action) {
  const params = new URLSearchParams();
  params.append("action", String(action ?? ""));

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
        <td>${escapeHtml(item.nombre_producto ?? "-")}</td>
        <td>${escapeHtml(item.total_vendido ?? 0)}</td>
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
        <td colspan="4" style="text-align:center;">Sin ventas registradas.</td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = rows
    .map((item) => `
      <tr>
        <td>${escapeHtml(item.id ?? 0)}</td>
        <td>${escapeHtml(`${item.nombre ?? ""} ${item.apellido ?? ""}`.trim() || "-")}</td>
        <td>${escapeHtml(formatMoney(item.total ?? 0))}</td>
        <td>${escapeHtml(item.fecha_venta ?? "-")}</td>
      </tr>
    `)
    .join("");
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

  renderHomeUltimaVenta(data.ultimasVentas);
  renderHomePedidosEstadoSummary(data.pedidosEstados);
  renderHomeTopProductos(data.topProductos);
  renderHomeUltimasVentas(data.ultimasVentas);
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

if (content && !content.innerHTML.trim()) {
  loadRouteContent("home").catch((error) => console.error(error));
}

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
        <td colspan="4" style="text-align:center;">Este pedido no tiene productos registrados.</td>
      </tr>
    `;
    initTablePagination(content);
    return;
  }

  itemsContainer.innerHTML = detalles
    .map((item) => `
      <tr>
        <td>${escapeHtml(item.nombre_producto ?? "-")}</td>
        <td>${escapeHtml(item.cantidad ?? 0)}</td>
        <td>${formatMoney(item.precio_unitario)}</td>
        <td>${formatMoney(item.subtotal)}</td>
      </tr>
    `)
    .join("");
  initTablePagination(content);
}

function fillPedidoEditForm(pedido) {
  const idInput = document.querySelector("#pedido-edit-id");
  const estadoSelect = document.querySelector("#pedido-edit-estado");
  const submitBtn = document.querySelector("#pedido-edit-submit");
  const feedback = document.querySelector("#pedido-edit-feedback");

  if (!idInput || !estadoSelect || !submitBtn) return;

  idInput.value = String(pedido.id ?? "");
  const estadoActual = normalizeEstadoValue(pedido.estado);

  if ([...estadoSelect.options].some((opt) => opt.value === estadoActual)) {
    estadoSelect.value = estadoActual;
  } else {
    estadoSelect.value = "procesando";
  }

  submitBtn.disabled = false;
  submitBtn.textContent = "Guardar cambios";
  if (feedback) {
    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("error", "success");
  }
}

function setPedidoEditFeedback(message, type = "error") {
  const feedback = document.querySelector("#pedido-edit-feedback");
  if (!feedback) return;

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

function getVentaCreatePayload() {
  const idPedido = Number(document.querySelector("#venta-create-pedido")?.value ?? 0);
  const totalRaw = normalizePriceInput(document.querySelector("#venta-create-total")?.value);
  const total = Number(totalRaw);
  const metodoPago = normalizeEstadoValue(document.querySelector("#venta-create-metodo")?.value ?? "efectivo");

  return {
    idPedido,
    totalRaw,
    total,
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

function updateVentaCreateTotalFromPedido() {
  const select = document.querySelector("#venta-create-pedido");
  const totalNode = document.querySelector("#venta-create-total");
  if (!select || !totalNode) return;

  const option = select.options[select.selectedIndex];
  const total = Number(option?.dataset?.total ?? 0);
  if (Number.isFinite(total) && total > 0) {
    totalNode.value = total.toFixed(2);
  } else if (!select.value) {
    totalNode.value = "";
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

async function requestVentaCreate(payload) {
  const params = new URLSearchParams();
  params.append("id_pedido", String(payload.idPedido));
  params.append("total", payload.totalRaw);
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
  const estado = normalizeEstadoValue(document.querySelector("#producto-create-estado")?.value ?? "activo");

  return {
    idCategoria,
    nombreProducto,
    descripcion,
    precioBaseRaw,
    precioBase,
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
  const estado = normalizeEstadoValue(document.querySelector("#producto-edit-estado")?.value ?? "activo");

  return {
    id,
    idCategoria,
    nombreProducto,
    descripcion,
    precioBaseRaw,
    precioBase,
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
  if (estadoNode) estadoNode.value = "activo";

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
  const estadoNode = document.querySelector("#producto-edit-estado");
  const submitBtn = document.querySelector("#producto-edit-submit");

  if (idNode) idNode.value = String(data.id ?? "");
  if (categoriaNode) categoriaNode.value = String(data.idCategoria ?? "");
  if (nombreNode) nombreNode.value = String(data.nombreProducto ?? "");
  if (descripcionNode) descripcionNode.value = String(data.descripcion ?? "");
  if (precioNode) precioNode.value = String(data.precioBase ?? "");
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

function getPedidoItemRows() {
  return [...document.querySelectorAll("#pedido-items .pedido-item-row")];
}

function getProductoPrecioFromRow(row) {
  const select = row.querySelector(".pedido-item-producto");
  if (!select) return 0;
  const option = select.options[select.selectedIndex];
  const precio = Number(option?.dataset?.precio ?? 0);
  return Number.isFinite(precio) ? precio : 0;
}

function updatePedidoCreateTotal() {
  const totalNode = document.querySelector("#pedido-create-total-valor");
  if (!totalNode) return;

  const total = getPedidoItemRows().reduce((sum, row) => {
    const cantidadNode = row.querySelector(".pedido-item-cantidad");
    const cantidad = Number(cantidadNode?.value ?? 0);
    const precio = getProductoPrecioFromRow(row);
    if (!Number.isFinite(cantidad) || cantidad <= 0) return sum;
    return sum + (cantidad * precio);
  }, 0);

  totalNode.textContent = formatMoney(total);
}

function addPedidoItemRow() {
  const container = document.querySelector("#pedido-items");
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

  container.appendChild(clone);
  updatePedidoCreateTotal();
}

function removePedidoItemRow(targetButton) {
  const rows = getPedidoItemRows();
  if (rows.length <= 1) return;
  const row = targetButton.closest(".pedido-item-row");
  if (row) {
    row.remove();
    updatePedidoCreateTotal();
  }
}

function getPedidoCreatePayload() {
  const clienteNode = document.querySelector("#pedido-create-cliente");
  const estadoNode = document.querySelector("#pedido-create-estado");

  const idCliente = Number(clienteNode?.value ?? 0);
  const estado = normalizeEstadoValue(estadoNode?.value ?? "pendiente");

  const items = getPedidoItemRows()
    .map((row) => {
      const idProducto = Number(row.querySelector(".pedido-item-producto")?.value ?? 0);
      const cantidad = Number(row.querySelector(".pedido-item-cantidad")?.value ?? 0);
      return {
        id_producto: idProducto,
        cantidad
      };
    })
    .filter((item) => item.id_producto > 0 && item.cantidad > 0);

  return { idCliente, estado, items };
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

function resetPedidoCreateForm() {
  const form = document.querySelector("#pedido-create-form");
  const container = document.querySelector("#pedido-items");

  if (!form || !container) return;

  form.reset();

  const rows = getPedidoItemRows();
  rows.forEach((row, index) => {
    if (index > 0) row.remove();
  });

  const firstRow = container.querySelector(".pedido-item-row");
  if (firstRow) {
    const select = firstRow.querySelector(".pedido-item-producto");
    const cantidad = firstRow.querySelector(".pedido-item-cantidad");
    if (select) select.value = "";
    if (cantidad) cantidad.value = "1";
  }

  setPedidoCreateFeedback("", "error");
  const submitBtn = document.querySelector("#pedido-create-submit");
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = "Crear Pedido";
  }

  updatePedidoCreateTotal();
}

async function updatePedidoEstado(idPedido, estado) {
  const params = new URLSearchParams();
  params.append("id", idPedido);
  params.append("estado", estado);

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

function refreshPedidoEstadoInRow(idPedido, estado) {
  const editBtn = content
    ? [...content.querySelectorAll('[data-action="editar-pedido"]')]
        .find((btn) => String(btn.dataset.id) === String(idPedido))
    : null;
  const row = editBtn?.closest("tr");
  if (!row) return;

  const statusSpan = row.querySelector('span[class^="status-"]');
  if (!statusSpan) return;

  statusSpan.className = `status-${toEstadoClass(estado)}`;
  statusSpan.textContent = capitalize(normalizeEstadoValue(estado));
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

function renderHistorialDetalleItems(detalle) {
  const tbody = document.querySelector("#historial-detalle-items-body");
  if (!tbody) return;

  const rows = Array.isArray(detalle) ? detalle : [];
  if (rows.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align:center;">Sin detalle para mostrar.</td>
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
        <td>${escapeHtml(item.nombre_producto ?? "-")}</td>
        <td>${escapeHtml(item.cantidad ?? 0)}</td>
        <td>${escapeHtml(formatMoney(item.precio_unitario ?? 0))}</td>
        <td>${escapeHtml(formatMoney(subtotal))}</td>
        <td>${escapeHtml(formatMoney(totalExtra))}</td>
        <td>${escapeHtml(formatMoney(totalLinea))}</td>
        <td>${escapeHtml(item.personalizaciones || "-")}</td>
      </tr>
    `;
  }).join("");
}

function fillHistorialDetail(registro, detalle = [], resumen = {}) {
  const cliente = `${registro.nombre ?? ""} ${registro.apellido ?? ""}`.trim();
  const responsable = String(registro.usuario_responsable_nombre ?? "").trim() || "Sistema";
  const estadoHistorial = capitalize(normalizeEstadoValue(registro.estado || "registrado"));
  const estadoPedido = capitalize(normalizeEstadoValue(registro.estado_pedido || "pendiente"));
  const metodoPago = capitalize(normalizeEstadoValue(registro.metodo_pago || "no definido"));

  const totalItems = Number(resumen.total_items ?? registro.total_items ?? 0);
  const totalPrendas = Number(resumen.total_prendas ?? registro.total_prendas ?? 0);
  const subtotalProductos = Number(resumen.subtotal_productos ?? 0);
  const totalExtras = Number(resumen.total_extras ?? 0);
  const totalCalculado = Number(resumen.total_calculado ?? (subtotalProductos + totalExtras));

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
  setNodeText("#historial-detalle-estado", estadoHistorial);
  setNodeText("#historial-detalle-estado-pedido", estadoPedido);
  setNodeText("#historial-detalle-total-items", totalItems);
  setNodeText("#historial-detalle-total-prendas", totalPrendas);
  setNodeText("#historial-detalle-subtotal", formatMoney(subtotalProductos));
  setNodeText("#historial-detalle-extras", formatMoney(totalExtras));
  setNodeText("#historial-detalle-total-calculado", formatMoney(totalCalculado));

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

  const anularBtn = row.querySelector('[data-action="anular-historial"]');
  if (anularBtn) {
    anularBtn.remove();
  }

  const detailId = document.querySelector("#historial-detalle-id");
  const detailEstado = document.querySelector("#historial-detalle-estado");
  if (detailId && detailEstado && String(detailId.textContent ?? "") === String(idHistorial)) {
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

    const newVentaBtn = e.target.closest('[data-action="nueva-venta"]');
    if (newVentaBtn) {
      resetVentaCreateForm();
      openVentaCreateModal();
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
      addPedidoItemRow();
      return;
    }

    const removeItemBtn = e.target.closest('[data-action="quitar-item-pedido"]');
    if (removeItemBtn) {
      removePedidoItemRow(removeItemBtn);
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
            <td colspan="4" style="text-align:center;">Cargando...</td>
          </tr>
        `;
      }

      try {
        await loadPedidoDetail(idPedido);
      } catch (error) {
        if (itemsContainer) {
          itemsContainer.innerHTML = `
            <tr>
              <td colspan="4" style="text-align:center;">${escapeHtml(error.message)}</td>
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
        fillPedidoEditForm(data.pedido || {});
      } catch (error) {
        setPedidoEditFeedback(error.message, "error");
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = "Error";
        }
      }
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

    if (closeBtnModal?.dataset?.close === "cliente-create-modal") {
      closeClienteCreateModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "cliente-edit-modal") {
      closeClienteEditModal();
      return;
    }

    if (closeBtnModal?.dataset?.close === "historial-modal") {
      closeHistorialModal();
    }
  });

  content.addEventListener("change", (e) => {
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
    }
  });

  content.addEventListener("input", (e) => {
    if (e.target.closest("#pedido-create-form")) {
      if (e.target.matches(".pedido-item-cantidad")) {
        updatePedidoCreateTotal();
      }
    }
  });

  content.addEventListener("submit", async (e) => {
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

    const formData = new FormData(form);
    const idPedido = String(formData.get("id") ?? "");
    const estado = normalizeEstadoValue(formData.get("estado"));

    try {
      const data = await updatePedidoEstado(idPedido, estado);
      refreshPedidoEstadoInRow(data.id ?? idPedido, data.estado ?? estado);
      setPedidoEditFeedback("Estado actualizado correctamente.", "success");
      closePedidoEditModal();
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
    closePedidoModal();
    closePedidoEditModal();
    closePedidoCreateModal();
    closeVentaCreateModal();
    closeVentaEditModal();
    closeCategoriaCreateModal();
    closeCategoriaEditModal();
    closeProductoCreateModal();
    closeProductoEditModal();
    closePerfilCreateModal();
    closePerfilEditModal();
    closeClienteCreateModal();
    closeClienteEditModal();
    closeHistorialModal();
  }
});

// Logout action
if (logoutBtn) {
  const goLogout = () => {
    window.location.href = "?route=logout";
  };

  logoutBtn.addEventListener("click", goLogout);
  logoutBtn.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      goLogout();
    }
  });
}
