function loadInstanceStatus(item) {
    item.addClass('loading');
    $.ajax({
        accepts: {
            mycustomtype: 'application/json'
        },
        url: '/api/instance/status',
        data: {instanceid: item.data('id')},
        success: function (data) {
            item.find('.list-view-pf-additional-info').html(data);
            item.removeClass('loading');
            item.addClass('done');
        }
    });
}



function initActionButtons(item) {

    // click the list-view heading then expand a row
    item.find(".list-group-item-header").click(function (event) {
        if (!$(event.target).is("button, a, input, .fa-ellipsis-v")) {
            $(this).find(".fa-angle-right").toggleClass("fa-angle-down")
                .end().parent().toggleClass("list-view-pf-expand-active")
                .find(".list-group-item-container").toggleClass("hidden");
        }
    });

    // click the close button, hide the expand row and remove the active status
    item.find(".list-group-item-container .close").on("click", function () {
        $(this).parent().addClass("hidden")
            .parent().removeClass("list-view-pf-expand-active")
            .find(".fa-angle-right").removeClass("fa-angle-down");
    });

    item.find(".list-view-pf-actions .dropdown-menu a[data-action]").on('click', function (e) {
        e.preventDefault();
        item.addClass('loading');

        let method = $(this).data('method') ? $(this).data('method') : 'put';
        let instance = $(this).parents('.instance');
        $.ajax({
            accepts: {
                mycustomtype: 'application/json'
            },
            url: '/api/' + $(this).data('action'),
            data: {instanceid: item.data('id')},
            method: method,
            success: function (data) {
                if (method === 'delete') {
                    instance.addClass('hidden');
                }

                if (data.hasOwnProperty('notification')) {
                    $(data['notification']).prependTo($('body'));
                }
            },
            complete: function () {
                item.removeClass('loading');
            }
        })
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

