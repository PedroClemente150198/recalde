

// Get the sidebar, close button, and search button elements
let sidebar = document.querySelector(".sidebar");
let closeBtn = document.querySelector("#btn");
let searchBtn = document.querySelector(".bx-search");
let navList = document.querySelector(".nav-list");

// Event listener for the menu button to toggle the sidebar open/close
closeBtn.addEventListener("click", () => {
  sidebar.classList.toggle("open"); // Toggle the sidebar's open state
  navList.classList.toggle("scroll"); // Toggle scroll state
  menuBtnChange(); // Call function to change button icon
});

// Event listener for the search button to open the sidebar
searchBtn.addEventListener("click", () => {
  sidebar.classList.toggle("open");
  navList.classList.toggle("scroll");
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

document.querySelectorAll('.nav-list a').forEach(link => {
    link.addEventListener('click', async (e) => {
        e.preventDefault();

        //console.log(link);

        const a = e.target.closest("a");
        let page = a.dataset.page;

        if (!page) return; // evita errores

        const html = await fetch(`?route=${page}`).then(r => r.text());
        document.querySelector("#content").innerHTML = html;
    });
});
