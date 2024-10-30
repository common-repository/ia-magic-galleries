'use strict';

window.presentation_full_loaded = true;

if (IA_Designer) {
    console.log("Presentation Full loaded!!");

    const addFullStyle = () => {
        let css = 'body, html {\n' +
            '            height: 100%;\n' +
            '            overflow: hidden; \n' +
            '            margin: 0;\n' +
            '        }\n' +
            '.IA_Presenter_Container {\n' +
            '        position: fixed;\n' +
            '        width: 100%;\n' +
            '        height: 100%;\n' +
            '        top: 50%;\n' +
            '        left: 50%;\n' +
            '        transform: translate(-50%, -50%);\n' +
            '    }\n ' +
            '#wpadminbar{' +
            '   opacity: .3;\n' +
            '}';
        const head = document.head || document.getElementsByTagName('head')[0],
            style = document.createElement('style');

        head.appendChild(style);

        style.appendChild(document.createTextNode(css));
    };;

    const hideAdminBar = () => {

    };

    const make_full = function (){
        const gui = this;

        console.log("in make_full");
        if (gui.div_container.attr("behaviour") !== "full") return;

        let div_parent = gui.div_container.node.parentNode;
        if (div_parent.parentNode) {
            addFullStyle();
            var savedNode = div_parent.parentNode.removeChild(div_parent);

            let children = Array.from(document.body.children);

            for (let i = 0; i < children.length; i++) {
                let target = children[i];

                if (target.tagName.toLowerCase() === "script"
                    || target.tagName.toLowerCase() === "style") continue;

                if (target.id === "wpadminbar") {
                    hideAdminBar();
                    continue;
                }
                document.body.removeChild(target);
            }

            document.body.appendChild(savedNode);

            gui.eve('panel.resize');
        }
    };

    let gui = IA_Designer.getGui();
    if (gui){
        make_full.call(gui)
    } else {
        IA_Designer.eve.on(['ia', 'gui', 'created'], make_full)
    }

}