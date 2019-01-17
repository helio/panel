$(document).ready(function () {
    loadList('user/joblist', '#helio-joblist');
    new wizard(".btn.wizard-pf-addJob");

    $('#helio-joblist .loadmore').click(function () {
        loadList('user/joblist', '#helio-joblist');
    });

    $('#helio-joblist').on('moreLoaded', function () {
        $('#helio-joblist .job:not(.done)').each(function () {
            initActionButtons($(this))
        });

        // make code pretty
        prettyPrint();
    });

});