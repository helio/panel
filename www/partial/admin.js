// register and call stuff
$(document).ready(function () {
    $('#helio-instancelist .loadmore').click(function () {
        loadList('admin/instancelist', '#helio-instancelist');
    });

    $('#helio-instancelist').on('moreLoaded', function () {
        initActionButtons($(this));
        prettyPrint();
    });


    loadList('admin/joblist', '#helio-joblist');
    loadList('admin/instancelist', '#helio-instancelist');

    $('#helio-joblist .loadmore').click(function () {
        loadList('admin/joblist', '#helio-joblist');
    });

    $('#helio-joblist').on('moreLoaded', function () {
        // load some instances if none already loaded (fresh pageload)
        // note: This cannot happen on document.ready as well, since token expiration race conditinos could occur
        if ($('#helio-instancelist').data('loaded') + 0 === 0) {
            loadList('admin/instancelist', '#helio-instancelist');
        }

        $(this).find('form.dispatch').on('submit', function (e) {
            e.preventDefault();
            let data = $(this).serialize() + '&jobid=' + $(this).parents('.job').data('id');


            $.ajax({
                accepts: {
                    mycustomtype: 'application/json'
                },
                url: '/api/admin/dispatch',
                data: data,
                method: 'PUT',
                success: function (data) {
                    if (data.hasOwnProperty('notification')) {
                        $(data['notification']).prependTo($('#notificationContainer'));
                    }
                }
            })


        });

        initActionButtons($(this));
        prettyPrint();
    });
});