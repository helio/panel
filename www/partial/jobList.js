

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
        let job = $(this).parents('.job');
        $.ajax({
            accepts: {
                mycustomtype: 'application/json'
            },
            url: '/api/' + $(this).data('action'),
            data: {jobid: item.data('id')},
            method: method,
            success: function (data) {
                if (method === 'delete') {
                    job.addClass('hidden');
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