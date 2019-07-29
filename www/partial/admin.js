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

        initActionButtons($(this));
        prettyPrint();
    });


    $('.impersonate').on('click', function (e) {
        let button = $(this);
        let userId = button.data('userid');
        document.cookie = 'impersonate=' + userId + '; path=/';
        window.location = '/';
    });

    $('.generate-eternal-token').on('click', function (e) {
        e.preventDefault();
        let button = $(this);
        let userId = button.data('userid');
        $.ajax({
            accepts: {
                mycustomtype: 'application/json'
            },
            url: '/api/user/settoken?eternal=true',
            method: 'put',
            success: function (data) {
                if (data.hasOwnProperty('notification')) {
                    $(data.notification).prependTo($('body'));
                }
            }
        })
    });

    $('.generate-instance-token').on('click', function (e) {
        e.preventDefault();
        let button = $(this);
        let instanceId = $('#instanceid').val();
        $.ajax({
            accepts: {
                mycustomtype: 'application/json'
            },
            url: '/api/admin/getinstancetoken?instanceid=' + instanceId,
            method: 'get',
            success: function (data) {
                if (data.hasOwnProperty('notification')) {
                    $(data.notification).prependTo($('body'));
                }
            }
        })
    });

    $('.generate-job-token').on('click', function (e) {
        e.preventDefault();
        let button = $(this);
        let jobId = $('#jobid').val();
        $.ajax({
            accepts: {
                mycustomtype: 'application/json'
            },
            url: '/api/admin/getjobtoken?jobid=' + jobId,
            method: 'get',
            success: function (data) {
                if (data.hasOwnProperty('notification')) {
                    $(data.notification).prependTo($('body'));
                }
            }
        })
    });
});