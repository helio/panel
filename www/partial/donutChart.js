const donutInit = function (donutCfg, donutData) {
    donutData.type = 'donut';

    let c3ChartDefaults = $().c3ChartDefaults();

    // Right Legend
    let donutChartRightConfig = c3ChartDefaults.getDefaultRelationshipDonutConfig();
    donutChartRightConfig.bindto = donutCfg.domid;
    donutChartRightConfig.tooltip = {show: true};
    donutChartRightConfig.data = donutData;
    donutChartRightConfig.legend = {
        show: true,
        position: 'right'
    };
    donutChartRightConfig.size = {
        width: 251,
        height: 161
    };
    donutChartRightConfig.tooltip = {
        contents: $().pfDonutTooltipContents
    };

    c3.generate(donutChartRightConfig);
    $().pfSetDonutChartTitle(donutCfg.domid, donutCfg.total, donutCfg.label);
};

$(document).ready(function () {
    $('.donut-chart').each(function () {
        let i = $(this);

        let cfg = {
            total: i.data('total'),
            label: i.data('label'),
            domid: '#' + i.attr('id')
        };

        let data = {
            columns: []
        };
        $(i.data('columns').split(',')).each(function () {
            data.columns.push(this.split(':'));
        });

        donutInit(cfg, data);
    });
});