'use strict';

window.presentation_expander_loaded = true;
console.log("Presentation Expander loaded!!");

if (IA_Designer) {

    const adaptive_guis = {};

    document.body.style.overflowX = "hidden";

    const widthFunctionPercent = (p, parent) => {
        p = p || 1;
        // let width = jQuery(window).width();
        let width = (parent) ? window.getComputedStyle(parent).width : document.body.clientWidth;
        if (typeof width === "string" && width.endsWith("px")) {
            width = +width.slice(0, -2);
        }
        // console.log(width);
        return width * p;
    };

    let resize_time_default = 1000;

    const adminbar = jQuery('#wpadminbar');

    const edit_header = jQuery('.edit-post-header');


    const admin_bar_height = () => {
        let admin_bar_h = 0;
        if (adminbar.length) {
            admin_bar_h = adminbar.height();
        }
        if (edit_header.length) {
            admin_bar_h = Math.max(admin_bar_h, edit_header.height());
        }

        const toolbar = jQuery('.block-editor-block-contextual-toolbar.is-fixed');
        console.log(toolbar);
        if (toolbar.length) {
            console.log("Toolbar", toolbar.height())
            admin_bar_h += toolbar.height() + 5;
        }

        return admin_bar_h;
    };

    const valFunction = w => w;

    const heightFunctionPersent = p => {
        p = p || 1;
        return (window.innerHeight - admin_bar_height()) * p;
    };

    const make_full_width = (element, remove) => {
        if (remove) {
            // element.classList.remove("alignfull")
            element.classList.remove('IA_Designer_Full_Width');
            element.setAttribute('data-align', '')
            element.style.width = null;
            element.style.maxWidth = null;
            element.removeAttribute('data-disable-border')
            // element.
        } else {
            // element.classList.add("alignfull")

            element.setAttribute('x', 'full')

            // element.classList.add('IA_Designer_Full_Width');

            element.setAttribute('data-disable-border', '1')

            // element.style.width = "100vw"
            // element.style.maxWidth = "100vw"
        }
    };

    const set_adaptive = (guis, gui) => {
        for (let gui_id in guis) if (guis.hasOwnProperty(gui_id)) {
            const parent = guis[gui_id].div_container.node.parentNode;
            // let in_gutenburg = parent.classList.contains('wp-block');
            if (guis[gui_id] === gui) {

                // gui.div_container.classList.add('IA_Designer_Full_Width');
                // in_gutenburg &&
                make_full_width(parent)

                let w = gui.div_container.attr("data-width") || "100%";

                if (w.endsWith("%")) {
                    w = +w.slice(0, -1)
                    w = Math.min(w / 100, 1);
                    gui.setWidthFunction(widthFunctionPercent.bind(undefined, w, parent));
                } else {
                    gui.setWidthFunction(valFunction.bind(undefined, +w));
                }


                let h = gui.div_container.attr("data-height") || "100%";

                if (h.endsWith("%")) {
                    h = +h.slice(0, -1);
                    h = Math.min(h / 100, 1);
                    gui.setHeightFunction(heightFunctionPersent.bind(undefined, h));
                } else {
                    gui.setHeightFunction(valFunction.bind(undefined, +h));
                }

                const resize = gui.div_container.attr("data-resize-time") || resize_time_default;

                gui.eve('panel.resize', undefined, undefined, resize);

                setTimeout(() => gui.div_container.node.scrollIntoView(
                    {behavior: 'smooth', block: 'center'}), 50);

            } else {
                guis[gui_id].div_container.removeClass('IA_Designer_Full_Width');
                // in_gutenburg &&
                make_full_width(parent, true);
                guis[gui_id].setWidthFunction(undefined);
                guis[gui_id].setHeightFunction(undefined);
                guis[gui_id].eve('panel.resize', undefined, undefined, 0);
            }
        }
    };

    let current_active;

    var activate = function (id) {
        if (current_active === id) return;
        console.log('In Activation ............', admin_bar_height());
        const gui = this;

        let behaviour = gui.div_container.attr("behaviour");
        if (behaviour === "adaptive") {
            if (!adaptive_guis[id]) {
                adaptive_guis[id] = gui;
            }
            set_adaptive(adaptive_guis, gui);
        }

        current_active = id;
    };

    IA_Designer.eve.on(['ia', 'setActiveInterface'], activate);

}