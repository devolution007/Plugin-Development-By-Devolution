(function (wp, cfg) {
    'use strict';

    var el = wp.element.createElement;
    var __ = wp.i18n.__;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var ToggleControl = wp.components.ToggleControl;
    var TextControl = wp.components.TextControl;
    var RangeControl = wp.components.RangeControl;
    var SelectControl = wp.components.SelectControl;
    var ServerSideRender = wp.serverSideRender;

    function options(list, allLabel) {
        return [{ label: allLabel, value: '' }].concat((list || []).map(function (v) { return { label: v, value: v }; }));
    }

    wp.blocks.registerBlockType('monthly-achievers/achievers', {
        title: __('Monthly Achievers', 'monthly-achievers'),
        description: __('Students, Huffaz and Employees of the Month in tabs with a carousel.', 'monthly-achievers'),
        icon: 'awards',
        category: 'widgets',
        attributes: {
            groups: { type: 'array', items: { type: 'string' }, default: ['students', 'huffaz', 'employees'] },
            month: { type: 'string', default: 'latest' },
            campus: { type: 'string', default: '' },
            perPage: { type: 'number', default: 3 }
        },
        edit: function (props) {
            var a = props.attributes;
            var set = props.setAttributes;

            function toggle(key) {
                var has = a.groups.indexOf(key) !== -1;
                var next = has ? a.groups.filter(function (g) { return g !== key; }) : a.groups.concat([key]);
                if (next.length) set({ groups: next }); // keep at least one group
            }

            var groupToggles = Object.keys(cfg.groups || {}).map(function (key) {
                return el(ToggleControl, {
                    key: key,
                    label: cfg.groups[key],
                    checked: a.groups.indexOf(key) !== -1,
                    onChange: function () { toggle(key); }
                });
            });

            return el('div', useBlockProps(),
                el(InspectorControls, null,
                    el(PanelBody, { title: __('Groups', 'monthly-achievers'), initialOpen: true }, groupToggles),
                    el(PanelBody, { title: __('Display', 'monthly-achievers'), initialOpen: true },
                        el(TextControl, {
                            label: __('Month', 'monthly-achievers'),
                            help: __('"latest" or a month like 2026-10. Visitors can still switch months.', 'monthly-achievers'),
                            value: a.month,
                            onChange: function (v) { set({ month: v || 'latest' }); }
                        }),
                        el(RangeControl, {
                            label: __('Cards per view', 'monthly-achievers'),
                            min: 1, max: 6, value: a.perPage,
                            onChange: function (v) { set({ perPage: v || 3 }); }
                        }),
                        el(SelectControl, {
                            label: __('Only this campus', 'monthly-achievers'),
                            value: a.campus, options: options(cfg.campuses, __('All campuses', 'monthly-achievers')),
                            onChange: function (v) { set({ campus: v }); }
                        })
                    )
                ),
                el(ServerSideRender, { block: 'monthly-achievers/achievers', attributes: a })
            );
        },
        save: function () { return null; }
    });
})(window.wp, window.MA_Block || {});
