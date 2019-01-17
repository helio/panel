$(document).ready(function () {
    // matchHeight the contents of each .card-pf and then the .card-pf itself
    $(".row-cards-pf > [class*='col'] > .card-pf .card-pf-title").matchHeight();
    $(".row-cards-pf > [class*='col'] > .card-pf > .card-pf-body").matchHeight();
    $(".row-cards-pf > [class*='col'] > .card-pf > .card-pf-footer").matchHeight();
    $(".row-cards-pf > [class*='col'] > .card-pf").matchHeight();

    // Initialize the vertical navigation
    $().setupVerticalNavigation(true);

    $('.end-impersonation').on('click', function (e) {
        e.preventDefault();
        document.cookie = 'impersonate=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
        window.setTimeout(() => {
            window.location = '/';
        }, 150);
    });

    // initialize pretty print
    prettyPrint();
});

