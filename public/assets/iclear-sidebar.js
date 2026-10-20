(function () {
    'use strict';
    var navigation = document.getElementById('sidebarnav');
    if (!navigation) {
        return;
    }
    navigation.querySelectorAll('a.has-arrow').forEach(function (link) {
        var submenu = link.nextElementSibling;
        if (!submenu || submenu.tagName !== 'UL') {
            return;
        }
        function setExpanded(expanded) {
            submenu.classList.toggle('show', expanded);
            submenu.classList.remove('in');
            link.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            submenu.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
        setExpanded(submenu.classList.contains('show'));
        link.addEventListener('click', function (event) {
            var href = link.getAttribute('href');
            if (href && href !== '#' && href !== 'javascript:void(0)') {
                return;
            }
            event.preventDefault();
            setExpanded(link.getAttribute('aria-expanded') !== 'true');
        });
    });
})();
