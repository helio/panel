function loadInstanceStatus(item) {
    item.addClass('loading');
    $.ajax({
        accepts: {
            mycustomtype: 'application/json'
        },
        url: '/api/instance/status',
        data: {instanceid: item.data('id')},
        success: function (data) {
            if (data.hasOwnProperty('listItemHtml')) {
                let newContent = $(data.listItemHtml);
                initActionButtons(newContent);
                $(item).replaceWith(newContent);
            }
        }
    });
}

// register and call stuff
$(document).ready(function () {
    loadList('user/instancelist', '#helio-instancelist');
    new wizard(".btn.wizard-pf-addInstance");


    $('#helio-instancelist .loadmore').click(function () {
        loadList('user/instancelist', '#helio-instancelist');
    });

    $('#helio-instancelist').on('moreLoaded', function () {
        $('#helio-instancelist .instance:not(.done)').each(function () {
            initActionButtons($(this));
            loadInstanceStatus($(this));
        });

        // make code pretty, also in server details
        prettyPrint();
    });
});

