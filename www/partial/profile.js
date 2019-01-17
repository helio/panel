$(document).ready(function () {
    $('.selectpicker').selectpicker();

    $('#form-profile').on('submit', function (e) {
        e.preventDefault();
        let form = $('#form-profile');
        form.addClass('loading');

        let data = form.serialize();
        $.ajax({
            accepts: {
                mycustomtype: 'application/json'
            },
            url: '/api/user/update',
            data: data,
            method: 'post',
            success: function (data) {
                if (data.hasOwnProperty('notification')) {
                    $(data.notification).prependTo($('body'));
                }
            },
            complete: function () {
                form.removeClass('loading').addClass('done');
            }
        })
    });

    $('#settoken').on('click', function (e) {

        $.ajax({
            accepts: {
                mycustomtype: 'application/json'
            },
            url: '/api/user/settoken',
            method: 'put',
            success: function (data) {
                if (data.hasOwnProperty('notification')) {
                    $(data.notification).prependTo($('body'));
                }
            }
        })
    });
});