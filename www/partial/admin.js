// register and call stuff
$(document).ready(function () {
    $('#helio-instancelist .loadmore').click(function () {
        loadList('admin/instancelist', '#helio-instancelist');
    });

    $('#helio-instancelist').on('moreLoaded', function () {
        // make code pretty, also in server details
        prettyPrint();
    });


    loadList('admin/joblist', '#helio-joblist');

    $('#helio-joblist .loadmore').click(function () {
        loadList('admin/joblist', '#helio-joblist');
    });

    $('#helio-joblist').on('moreLoaded', function () {

        // note: This cannot happen on document.ready as well, since token expiration race conditinos could occur
        if ($('#helio-instancelist').data('loaded') + 0 === 0) {
            loadList('admin/instancelist', '#helio-instancelist');
        }

        // make code pretty
        prettyPrint();
    });
});