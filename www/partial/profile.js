$(document).ready(function () {
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
            success: function () {
                $('<div class="toast-pf toast-pf-max-width toast-pf-top-right alert alert-success alert-dismissable">\n' +
                    '    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">' +
                    '        <span class="pficon pficon-close"></span>' +
                    '    </button>' +
                    '    <span class="pficon pficon-ok"></span>' +
                    '    Updated Successful' +
                    '</div>').prependTo($('body'));
            },
            complete: function () {
                form.removeClass('loading').addClass('done');
            }
        })
    });
});