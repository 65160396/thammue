(function () {
var path = location.pathname.replace(/\/+ /g, '/').toLowerCase();
document.querySelectorAll('.category-buttons a.category-button').forEach(function (a) {
var href = a.getAttribute('href').replace(/\/+ /g, '/').toLowerCase();
if (path.endsWith(href)) a.classList.add('active');
});
})();