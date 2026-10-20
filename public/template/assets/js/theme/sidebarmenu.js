function findMatchingElement() {
  var currentUrl = window.location.href;
  var anchors = document.querySelectorAll("#sidebarnav a");
  for (var i = 0; i < anchors.length; i++) {
    if (anchors[i].href === currentUrl) {
      return anchors[i];
    }
  }

  return null; // Return null if no matching element is found
}
var elements = findMatchingElement();
if (elements) {
  // Do something with the matching element
  elements.classList.add("active");
}
document
  .querySelectorAll("ul#sidebarnav ul li a.active")
  .forEach(function (link) {
    link.closest("ul").classList.add("in");
    link.closest("ul").parentElement.classList.add("selected");
  });

document.querySelectorAll("#sidebarnav a.has-arrow").forEach(function (link) {
  const submenu = link.nextElementSibling;
  if (!submenu || !submenu.classList.contains("collapse")) {
    return;
  }

  submenu.addEventListener("shown.bs.collapse", function () {
    link.setAttribute("aria-expanded", "true");
  });
  submenu.addEventListener("hidden.bs.collapse", function () {
    link.setAttribute("aria-expanded", "false");
  });

  link.addEventListener("click", function (event) {
    event.preventDefault();
    event.stopPropagation();

    if (window.bootstrap && window.bootstrap.Collapse) {
      window.bootstrap.Collapse.getOrCreateInstance(submenu, { toggle: false }).toggle();
      return;
    }

    const isExpanded = submenu.classList.contains("show");
    submenu.classList.toggle("show", !isExpanded);
    link.setAttribute("aria-expanded", isExpanded ? "false" : "true");
  });
});
