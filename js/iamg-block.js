/*
 * Copyright © 2024  Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */


(function () {
    let debug = false;
    console.log("Debug set",
        debug = true
    );

    const el = wp.element.createElement;
    const iap_settings = iap_loader_settings;
    debug && console.log('In iamg-block', iap_loader_settings);

    const __ = wp.i18n.__;
    let icon = {
        // Specifying a background color to appear with the icon e.g.: in the inserter.
        background: '#fff',
        // Specifying a color for the icon (optional: if not set, a readable color will be automatically defined)
        foreground: '#fff',
        // Specifying an icon for the block
        src: 'dashicons-format-image',

    };
    let post_id;

    let number_guis = 0;

    let glob_props = {};

    // window.glob_props = glob_props;

    let category = (iap_settings.wp_version && parseFloat(iap_settings.wp_version) < 5.8) ? 'widgets' : 'media';

    wp.blocks.registerBlockType('ia-mg/gallery', {
        title: __('IA Magic Gallery'),
        icon: 'format-gallery',//'universal-access-alt',
        category: category,
        supports: {
            align: true
        },
        example: {},
        attributes: {
            presentation: {type: 'string'},
            pres_id: {type: 'string'},
            block_id: {type: 'string'},
            behavior: {type: 'string', default: 'fixed'},
            height: {type: 'string', default: "100"},
            height_percent: {type: 'boolean', default: true},
            width: {type: 'string', default: "0"},
            width_percent: {type: 'boolean', default: true},
            properties: {type: 'string', default: ""},
            background_color: {type: 'string', default: "#fff"},
            background_opacity: {type: 'string', default: "0"}
        },
        usesContext: ['postId'],
        edit: edit,
        save: save,
    });

    function edit(props) {

        debug && console.log('In Edit', props.clientId,
            (props.isSelected) ? 'Selected' : 'Not Selected',
            // props.attributes.block_id,
            // props.attributes.pres_id,
            // props.attributes.properties,
            props
        );

        post_id = (props.context) ? props.context.postId : null;

        // post_id = props.context.postId;

        function updatePresentation(url) {
            props.setAttributes({presentation: url});
        }

        function updatePresentation_id(id) {
            props.setAttributes({pres_id: id});
        }

        function updateBlockId(id) {
            if (!props.attributes.block_id) props.setAttributes({block_id: id});
        }


        updateBlockId(props.clientId);
        if (!props.attributes.pres_id && props.attributes.block_id) {
            updatePresentation_id(props.attributes.block_id);
        }

        if (props.attributes.block_id && props !== glob_props[props.attributes.block_id]) {
            glob_props[props.attributes.block_id] = props;
        }


        const settings = Object.assign({}, iap_settings.settings);
        const loader_settgins = {
            load_condition: function () {
                const edit_class = 'interface-interface-skeleton__content';
                let selector = '.' + edit_class + ' .IA_Presenter_Container';
                let containers = document.querySelectorAll(selector);
                if (containers.length) return true;

                let iframe = document.querySelector('iframe[name="editor-canvas"]');
                if (iframe) {
                    let iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
                    let iframeContainers = iframeDocument.querySelectorAll('.IA_Presenter_Container');
                    return iframeContainers.length;
                }

                return false;
            },

            after_callback: load_iamg_block_events.bind(undefined, props.attributes),
            post_scripts: [
                "media_upload.min.js",
            ]
        };

        setTimeout(
            () => {
                if (window.IA_Presenter_loader) {
                    IA_Presenter_loader(settings, undefined, iap_settings.resources,
                        loader_settgins)
                } else {
                    debug && console.log("Presenter Loader Library not loaded");
                }

            }, 5);

        //Build div
        let height = +props.attributes.height;
        if (height && props.attributes.height_percent) {
            height = height + "vh";
        }
        if (!height || height === "100vh") {
            let admin_bar_height = 0;

            const adminbar = jQuery('.interface-interface-skeleton__header');
            if (adminbar.length) {
                admin_bar_height = adminbar.height()
            }
            const adminfoot = jQuery('.interface-interface-skeleton__footer');
            if (adminfoot.length) {
                admin_bar_height += adminfoot.height()
            }

            height = "calc(100vh - " + admin_bar_height + "px)";
        }

        let width = +props.attributes.width;
        let suffix = (props.attributes.width_percent) ? "vw" : "px";

        let style = {height: height};
        if (width) {
            style['width'] = width + suffix;
            style["margin-left"] = "calc(50% - " + (width / 2) + suffix + ")";
        }
        const html = el('div',
            {
                className: 'IA_Presenter_Container',
                'data-block-id': props.attributes.block_id,
                'presentation': (props.attributes.pres_id) ?
                    props.attributes.pres_id :
                    '',
                'behavior': 'fixed',
                style: style
            },

            //Removed until find fix
            // toggle_icon(props),

            // background_color_icon(props),
            set_fullscreen_button(props),
            toggle_height_menu(props),
            setting_bar_controller(props),
        );

        // debug && console.log("In Edit", html);

        return html;
    }

    function save(props) {

        if (props.attributes.pres_id) {

            let bh = {
                behavior: props.attributes.behavior,
                height: props.attributes.height,
                height_type: (props.attributes.height_percent) ? "percent" : "pixel",
                background_color: props.attributes.background_color,
                background_opacity: props.attributes.background_opacity
            };
            // if (props.attributes.width) {
            //     bh.width = props.attributes.width;
            //     bh.width_type = (props.attributes.width_percent) ? "percent" : "pixel";
            // }
            let behavior = process_behavior_object(bh);
            let text = '[ia_magic_gallery id="' + props.attributes.pres_id + '"' + behavior + ']';
            let element = el('div', {}, text);

            debug && console.log('In Save Fun', props.clientId, props, behavior, text);

            return element;
        } else {
            let element = el('div', {},
                '[ia_magic_gallery id="demo"]');

            return element;
        }
    }


    //LOADING EVENTS
    let events_loaded = false;

    function process_behavior_object(bh) {
        let output = " ";
        Object.keys(bh).forEach((key) => {
            const val = bh[key];
            if (typeof val === "object") {
                Object.keys(val).forEach((sub) => {
                    output += ' ' + key + '_' + sub + '="' + val[sub] + '"';
                })
            } else {
                output += ' ' + key + '="' + val + '"';
            }
        })

        return output;
    }

    function load_iamg_block_events(attributes) {
        if (events_loaded || !window.eve_ia) return;
        // let prev_properties = attributes.properties;
        events_loaded = true;

        //Events here

        eve_ia.on(['global', 'iamg', 'gallery', 'save'], function (locator) {
            const gui = this;
            const block_id = gui.getBlockId();
            const props = glob_props[block_id];

            let presId = props.attributes.pres_id;

            debug && console.log('Saving', block_id, presId, locator, glob_props, props);

            const command = {
                command: 'save',
                block_id: block_id,
                pres_id: presId,
                post_id: post_id,
                locator: locator,
            };

            let process_responce = function (response) {
                let margin = {
                    margin: {
                        top: 10,
                        bottom: 5,
                        left: 10,
                        right: 5,
                    }
                };

                if (response.error) {
                    debug && console.log('Error', response.error);

                    gui.eve("gui.alert", __("Gallery was not saved successfully") + ".", [220, 70], [__("OK")], [], margin)
                    gui.eve("gui.error", response.error)
                    if (submit_button) submit_button.disabled = false;
                    if (save_button) save_button.disabled = false;
                    return;
                }

                debug && console.log('Success', response);
                let server_settings = response["settings"] ? JSON.stringify(response["settings"]) : ""
                debug && console.log('Properties', server_settings)
                props.setAttributes({
                    properties: server_settings
                })
                debug && console.log("Saved the post on server", props);
                gui.eve("gui.alert", __("Gallery is saved successfully") + ".", [200, 70], [__("OK")], undefined,
                    margin)
                wp.data.dispatch('core/editor').savePost()
            };
            gui.comManager.wpCommand(command,
                process_responce,
                function (response) {
                    debug && console.log('Fail', response);
                },
            );

        });

        eve_ia.on(['global', 'iamg', 'gallery', 'removed'], function (locator) {
            const gui = this;
            const block_id = gui.getBlockId();
            const props = glob_props[block_id];

            let presId = props.attributes.pres_id;

            debug && console.log('Removing', block_id, presId);

            number_guis = Math.max(number_guis - 1, 0);

            const command = {
                command: 'remove',
                block_id: block_id,
                pres_id: presId,
                post_id: post_id,
                locator: locator,
            };

            // if (locator) {
            //     //todo
            // }
            gui.comManager.wpCommand(command,
                function (response) {
                    debug && console.log('Success', response);
                },
                function (response) {
                    debug && console.log('Fail', response);
                },
            );
        })

        eve_ia.on(['ia', 'gui', 'created'], function (id) {
            const gui = this;
            // debug && console.log('Creating gui..............', id);

            if (Snap_ia.window().MutationObserver) {
                let observer = new MutationObserver(function (mutationList) {
                    debug && console.log("Observed Div resize")
                    gui.eve('gui.panel.resize');
                },);

                const block_container = gui.div_container.node.parentNode;
                block_container && observer.observe(block_container, {
                    attributes: true, attributeOldValue: false, attributeFilter: ['data-align', 'class'],
                });
            }

            if (Snap_ia.window().ResizeObserver) {
                let observer = new ResizeObserver(function (mutationList) {
                    debug && console.log("Observed skeleton resize")
                    gui.eve('gui.panel.resize');
                },);
                const content_skel = document.getElementsByClassName("interface-interface-skeleton__content")[0];
                content_skel && observer.observe(content_skel)
            }
            number_guis++


            // debug && console.log("prev_properties", prev_properties);
            gui.eve.once(['presenter', 'loaded'], //['presenter', 'presentation', 'loaded']
                set_current_gallery_state.bind(undefined, gui))(100)
        })

    }


    function match_images(image_info, original_requested_images) {
        const images = []
        let original_length = original_requested_images.length;
        for (let i = 0, title; i < image_info.length; i++) {
            title = image_info[i].title;
            for (let j = 0; j < original_length; j++) {
                let originalRequestedImage = original_requested_images[(i + j) % original_length];
                if (title === originalRequestedImage.title) {
                    images.push({
                        id: title + "_" + originalRequestedImage.id,
                        url: image_info[i].thumbnail.url,
                        title: title
                    });
                    break;
                }
            }
        }
        return images;
    }

    function set_current_gallery_state(gui) {
        const block_id = gui.getBlockId();
        const props = glob_props[block_id];
        let attributes = props.attributes;
        let properties = attributes.properties;
        if (properties) properties = JSON.parse(properties)
        debug && console.log("Presenter loaded");
        debug && console.log("Setting previous properties: ", properties)

        if (attributes.background_color) gui.setPanelStyle({
            fill: attributes.background_color,
            fillOpacity: attributes.background_opacity / 100
        })

        if (!properties["type"]) return;

        const gallery_type = properties["type"];
        let image_info = properties["images"] || [];
        const original_requested_images = properties["requested_images"] || [];
        const resource = properties["resource"];

        let panel_state;
        for (let param in properties) if (properties.hasOwnProperty(param) && param.startsWith(gallery_type)) {
            panel_state = panel_state || {};
            panel_state[param] = properties[param];
        }

        let images;

        images = match_images(image_info, original_requested_images);

        gui.eve(["iamg", "load_gallery_setup"], undefined, gallery_type, images, panel_state, resource);
    }

    //GUTENBERG TOOLBAR FUNCTIONS

    let last_fixed_settings = {};

    function is_full_allowed() {
        return number_guis === 1;
    }

    function set_behavior(is_full, props, use_saved) {
        if (is_full && is_full_allowed()) {
            last_fixed_settings["height"] = props.attributes.height;
            last_fixed_settings["height_percent"] = props.attributes.height_percent;
            last_fixed_settings["width"] = props.attributes.width;
            last_fixed_settings["width_percent"] = props.attributes.width_percent;
            props.setAttributes({
                behavior: "full",
                height: 100,
                height_percent: true,
            })
        }
        if (!is_full) {
            if (use_saved && props.attributes.behavior === "full" && last_fixed_settings.hasOwnProperty("height")) {
                props.setAttributes({
                    height: last_fixed_settings.height,
                    height_percent: last_fixed_settings.height_percent,
                    width: last_fixed_settings.width,
                    width_percent: last_fixed_settings.width_percent,
                    behavior: "fixed"
                })
            } else {
                props.setAttributes({behavior: "fixed"})
            }
        }
        // debug && console.log("Atributes", props.attributes)
    }

    function toggle_icon() {
        const iconEl = el('svg',
            {width: 20, height: 20},
            el('path',
                {d: "M9.851,4.471c2.67,2.092,5.318,4.2,7.988,6.3l.049,4.5c-2.671-2.2-5.3-4.445-7.992-6.65C7.318,10.9,4.761,13.193,2.16,15.489c-.037-1.5-.032-3-.048-4.5C4.691,8.821,7.293,6.655,9.851,4.471ZM10.106,20q-2.227-1.751-4.48-3.5l-.039-2.805c1.5,1.21,2.979,2.429,4.481,3.638,1.435-1.261,2.869-2.533,4.3-3.795.026.934.027,1.868.04,2.8C13,17.559,11.546,18.779,10.106,20ZM20,0H0V1.2H20Z"}
            )
        );

        let blIconEl = el(wp.blockEditor.BlockIcon,
            {
                icon: iconEl,
            }
        );
        return el(
            wp.blockEditor.BlockControls,
            {key: 'toggle_toolbak'},
            el(wp.components.ToolbarButton, {
                    icon: blIconEl,
                    label: __("Toggle toolbar"),
                    onClick: () => {
                        wp.data.dispatch('core/edit-post').toggleFeature('fixedToolbar');
                        setTimeout(() => Object.values(IA_Designer.guis).forEach((gui) => gui.eve('gui.panel.resize')), 100)
                    }
                }
            ),
        );
    }

    function background_color_icon(props) {
        const iconEl = el('svg',
            {width: 20, height: 20},
            el('rect',
                {x: +0, y: +0, width: 15, height: 15, fill: 'blue'},
            )
        );

        let color = props.attributes.background_color

        let blIconEl = el(wp.blockEditor.BlockIcon,
            {
                icon: iconEl,
            }
        );

        let color_panel = el(
            wp.blockEditor.PanelColorSettings,
            {
                title: __('Color Settings'),
                colorSettings: [
                    {
                        value: color,
                        onChange: (colorValue) => {
                            colorValue(colorValue)
                            // setAttributes({color: colorValue})
                        },
                        label: __('Background Color'),
                    },
                    // {
                    //     value: textColor,
                    //     onChange: (colorValue) => {
                    //         debug && console.log(colorValue)
                    //         // setAttributes({textColor: colorValue})
                    //     },
                    //     label: __('Text Color'),
                    // },
                ],
            }
        );

        return el(
            wp.blockEditor.BlockControls,
            {key: 'backgrount_color'},
            el(wp.components.ToolbarDropdownMenu, {
                icon: blIconEl,
                label: __("Set Background Color"),
                // onClick: () => {
                //     wp.data.dispatch('core/edit-post').toggleFeature('fixedToolbar');
                //     setTimeout(() => Object.values(IA_Designer.guis).forEach((gui) => gui.eve('gui.panel.resize')), 100)
                // }
                controls:
                    [
                        color_panel
                    ]
            })
        )

    }

    function toggle_height_menu(props) {

        const is_full = props.attributes.behavior !== "fixed"
        if (is_full) {
            return
        }

        const iconEl = el('svg',
            {width: 20, height: 20},
            el('path',
                {
                    d: "M0,0v20h20V0H0z M19.5,19.5H0.5V0.5h19.1V19.5z M10.1,19.1C10,19.1,10,19.1,10.1,19.1c-0.2,0-0.4,0-0.5-0.1l-2.9-2.9c-0.2-0.2-0.2-0.5,0-0.7s0.5-0.2,0.7,0l2.1,2.1v-6.4c0-0.3,0.2-0.5,0.5-0.5s0.5,0.2,0.5,0.5v6.4l2.1-2.1c0.2-0.2,0.5-0.2,0.7,0s0.2,0.5,0,0.7l-2.9,2.9C10.3,19,10.2,19.1,10.1,19.1z M10,9.6c-0.3,0-0.5-0.2-0.5-0.5V2.7L7.4,4.8C7.2,5,6.9,5,6.7,4.8s-0.2-0.5,0-0.7l2.9-2.9C9.7,1.1,9.8,1.1,10,1.1c0.1,0,0.3,0,0.4,0.1l2.9,2.9c0.2,0.2,0.2,0.5,0,0.7s-0.5,0.2-0.7,0l-2.1-2.1v6.4C10.5,9.4,10.3,9.6,10,9.6z"
                }
            )
        );

        let blIconEl = el(wp.blockEditor.BlockIcon,
            {
                icon: iconEl,
            }
        );


        return el(
            wp.blockEditor.BlockControls,
            {key: 'height_menu'},

            el(wp.components.ToolbarDropdownMenu, {
                icon: blIconEl,
                label: __("Height (Check Settings for more options)"),
                // onClick: () => {
                //     wp.data.dispatch('core/edit-post').toggleFeature('fixedToolbar');
                //     setTimeout(() => Object.values(IA_Designer.guis).forEach((gui) => gui.eve('gui.panel.resize')), 100)
                // }
                controls:
                    [
                        {
                            title: '100%',
                            // icon: "arrowUp",
                            onClick: () => {
                                props.setAttributes({
                                    height: 100,
                                    height_percent: true
                                })
                                debug && console.log('100')
                            },
                        },
                        {
                            title: '75%',
                            // icon: "arrowRight",
                            onClick: () => {
                                props.setAttributes({
                                    height: 75,
                                    height_percent: true
                                })
                            },
                        },
                        {
                            title: '50%',
                            // icon: "arrowDown",
                            onClick: () => {
                                props.setAttributes({
                                    height: 50,
                                    height_percent: true
                                })
                            },
                        },
                        {
                            title: '25%',
                            // icon: "arrowLeft",
                            onClick: () => {
                                props.setAttributes({
                                    height: 25,
                                    height_percent: true
                                })
                            },
                        },
                    ]

            })
        )
    }

    function set_fullscreen_button(props) {
        if (!is_full_allowed()) return;
        const is_full = props.attributes.behavior !== "fixed"

        const iconEl = el('svg',
            {width: 20, height: 20},
            el('path',
                {d: "M6.368.937a1.016,1.016,0,0,1,.513.092.871.871,0,0,1,.256.919.482.482,0,0,1-.6.367c-.855,0-1.795.092-2.65.092l4.1,4.411a.692.692,0,0,1-.171,1.01.624.624,0,0,1-.855-.092L2.949,3.418c0,.919,0,1.838-.085,2.848a.866.866,0,0,1-.256.643c-.257.276-.684.184-.941-.183a1.253,1.253,0,0,1-.085-.552c0-1.47.085-2.94.085-4.41a.765.765,0,0,1,.684-.735C3.719,1.029,5.086,1.029,6.368.937Zm11.281.092a.7.7,0,0,1,.684.735c.085,1.47.085,2.94.085,4.41a1.253,1.253,0,0,1-.085.552.654.654,0,0,1-.941.183.739.739,0,0,1-.256-.643c0-.919,0-1.837-.085-2.848L13.034,7.736a.615.615,0,0,1-.855.092.692.692,0,0,1-.171-1.01l4.1-4.411c-.855,0-1.795-.092-2.65-.092a.566.566,0,0,1-.6-.367.682.682,0,0,1,.256-.919,1.016,1.016,0,0,1,.513-.092C15,1.029,16.281,1.029,17.649,1.029ZM2.351,18.854a.7.7,0,0,1-.684-.736c-.085-1.47-.085-2.94-.085-4.41a1.251,1.251,0,0,1,.085-.551.655.655,0,0,1,.941-.184.741.741,0,0,1,.256.643c0,.919,0,1.838.085,2.849l4.017-4.319a.616.616,0,0,1,.855-.091.692.692,0,0,1,.171,1.01l-4.1,4.41c.855,0,1.8.092,2.65.092a.567.567,0,0,1,.6.368.682.682,0,0,1-.256.919,1.026,1.026,0,0,1-.513.091C5.086,18.945,3.719,18.854,2.351,18.854Zm11.281.091a1.026,1.026,0,0,1-.513-.091.871.871,0,0,1-.256-.919.483.483,0,0,1,.6-.368c.855,0,1.8-.092,2.65-.092l-4.1-4.41a.692.692,0,0,1,.171-1.01.625.625,0,0,1,.855.091l4.017,4.319c0-.919,0-1.838.085-2.849a.869.869,0,0,1,.256-.643c.257-.275.684-.183.941.184a1.251,1.251,0,0,1,.085.551c0,1.47-.085,2.94-.085,4.41a.765.765,0,0,1-.684.736C16.281,18.854,15,18.945,13.632,18.945ZM20,20H0V0H20ZM.471,19.529H19.529V.471H.471Z"}
            ),
            is_full ?
                el('path', {
                    fill: "#BC1F47",
                    d: "M0.5,20.3c-0.2,0-0.4-0.1-0.5-0.2c-0.3-0.3-0.3-0.8,0-1.1L19-0.1c0.3-0.3,0.8-0.3,1.1,0" +
                        "s0.3,0.8,0,1.1L1,20.1C0.9,20.2,0.7,20.3,0.5,20.3z"
                })
                : null
        );

        let blIconEl = el(wp.blockEditor.BlockIcon,
            {
                icon: iconEl,
            }
        );

        return el(
            wp.blockEditor.BlockControls,
            {key: 'element_fullscreen'},
            el(wp.components.ToolbarButton, {
                    icon: blIconEl,
                    label: __(is_full ?
                        "Set to Fixed Placement on Page" :
                        "Set to Full Page Gallery (Alignment is disregarded)"),
                    onClick: () => {
                        //switch behavior
                        set_behavior(!is_full, props, true)
                    }
                }
            ),
        );
    }

    function setting_bar_controller(props) {
        let block_id = props.attributes.block_id;
        const show_height = props.attributes.behavior !== 'full';
        let classNameHeightPanel = 'IAMG_Height_Panel_' + block_id;
        let admin_bar_height = 0;
        // const adminbar = jQuery('.interface-interface-skeleton__header');
        // if (adminbar.length) {
        //     admin_bar_height = adminbar.height()
        // }
        let gui;
        if (window.IA_Designer) {
            gui = IA_Designer.getGui((gui) => gui.getBlockId() === block_id);
        }

        // debug && console.log(("In Setting Bar"), gui);

        const is_full = props.attributes.behavior !== "fixed"

        const is_percent_h = props.attributes.height_percent;
        let percent_or_px_button_h = (is_percent_h) ?
            el(wp.components.Button,
                {
                    isDefault: true,
                    onClick: () => {
                        props.setAttributes({height_percent: false});

                        let height = +props.attributes.height;
                        const to_pixel = height * (window.innerHeight - admin_bar_height) / 100;
                        props.setAttributes({height: String(to_pixel)});

                        // const to_percent = 100 * props.attributes.height / (window.innerHeight - admin_bar_height);
                        // props.setAttributes({height: to_percent});

                    }
                },
                "%")
            :
            el(wp.components.Button,
                {
                    isDefault: true,
                    onClick: () => {
                        props.setAttributes({height_percent: true});

                        // const to_pixel = props.attributes.height * (window.innerHeight - admin_bar_height) / 100;
                        // props.setAttributes({height: to_pixel});

                        let height = +props.attributes.height;
                        const to_percent = 100 * height / (window.innerHeight - admin_bar_height);
                        props.setAttributes({height: String(to_percent)});

                    },
                    marginTop: "auto"
                },
                "Px");

        let panel_height = (is_full) ? undefined
            : el(wp.components.PanelBody,
                {
                    title: __('Height'), initialOpen: true,
                    className: classNameHeightPanel,
                },
                el(wp.components.PanelRow, {},

                    el("div", {class: "IAMG_Layout_FlexRow"},
                        el(wp.components.TextControl,
                            {
                                // label: 'Height',
                                type: 'number',
                                onChange: (value) => {
                                    props.setAttributes({height: String(value)})
                                },
                                value: props.attributes.height || "",
                                class: "IAMG_Layout_ButtHeight"
                            }
                        ),
                        // el("div", {},
                        percent_or_px_button_h,
                        //     el("text", {},
                        //         (is_percent) ?
                        //             " Percent of window" :
                        //             " Pixels of Height")
                        // ),
                        // ),
                    )
                ),
                // el(wp.components.PanelRow, {},
                //
                // )
            );

        const is_percent_w = props.attributes.width_percent;
        let percent_or_px_button_w = (is_percent_w) ?
            el(wp.components.Button,
                {
                    isDefault: true,
                    onClick: () => {
                        props.setAttributes({width_percent: false});

                        const to_pixel = +props.attributes.width * window.innerWidth / 100;
                        props.setAttributes({width: String(to_pixel)});
                    }
                },
                "%")
            :
            el(wp.components.Button,
                {
                    isDefault: true,
                    onClick: () => {
                        props.setAttributes({width_percent: true});

                        const to_percent = 100 * +props.attributes.width / window.innerWidth;
                        props.setAttributes({width: String(to_percent)});
                    },
                    marginTop: "auto"
                },
                "Px");

        let panel_width = el(wp.components.PanelBody,
            {
                title: __('Width'), initialOpen: false,
                className: 'IAMG_Width_Panel_' + block_id,
            },
            el(wp.components.PanelRow, {},
                el("text", {},
                    "It is better to set width with block alignment for a standalone block! "),
            ),
            el(wp.components.PanelRow, {},
                el("text", {},
                    "Width must be set in the context of Row or Columns!"),
            ),
            el(wp.components.PanelRow, {},
                el("div", {class: "IAMG_Layout_FlexRow"},
                    el(wp.components.TextControl,
                        {
                            type: 'number',
                            onChange: (value) => {
                                props.setAttributes({width: value})
                            },
                            value: props.attributes.width || "",
                            class: "IAMG_Layout_ButtHeight"
                        }
                    ),
                    percent_or_px_button_w,
                )
            )//
        );

        let panel_layout = el(wp.components.PanelBody, {
                title: __('Layout'),
                initialOpen: true,
                className: 'IAMG_Layout_Panel_' + block_id,
            },
            el(wp.components.PanelRow, {},
                el(wp.components.ToggleControl,
                    {
                        label: __('Fixed (or Full)'),
                        onChange: (value) => {
                            set_behavior(value, props, true)
                        },
                        checked: props.attributes.behavior !== "fixed",
                    }
                )
            ),
            el(wp.components.PanelRow, {},
                el("text", {},
                    __((!is_full) ?
                        "The gallery will have fixed location in the page. Set the height behavior below!" :
                        "The gallery will take the full page. Only one full gallery can be placed on a page as all other " +
                        "elements will be removed."))
            ),
        );

        let color = props.attributes.background_color
        let color_panel = el(wp.components.PanelBody,
            {
                title: __('Background Color'),
                initialOpen: true,
            },
            el(wp.components.PanelRow, {},
                el(
                    wp.blockEditor.PanelColorSettings,
                    // wp.components.ColorPalette,
                    {
                        colorSettings: [
                            {
                                value: color,
                                onChange: (colorValue) => {
                                    // colorValue(colorValue)
                                    props.setAttributes({background_color: colorValue})
                                    if (gui) gui.setPanelStyle({fill: colorValue});
                                },
                                label: __('Color'),
                                // enableAlpha: true
                            }
                        ],
                        className: "IAMG_Layout_FullWidth"
                    },
                )
            ),
            el(wp.components.PanelRow, {},
                el(
                    wp.components.RangeControl,
                    {
                        label: __('Opacity'),
                        onChange: (value) => {
                            props.setAttributes({background_opacity: value})
                            if (gui) gui.setPanelStyle({fillOpacity: value / 100});
                        },
                        value: props.attributes.background_opacity || 0,
                        min: 0,
                        max: 100,
                        className: "IAMG_Layout_FullWidth"
                    }
                )
            )
        )
        return el(
            wp.blockEditor.InspectorControls, {},
            is_full_allowed() ? panel_layout : null,
            panel_height,
            panel_width,
            color_panel,
        )
    }


})();