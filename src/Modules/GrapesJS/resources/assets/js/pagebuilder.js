$(document).ready(function() {

    $(".gjs-editor").append($("#toggle-sidebar"));
    $(".gjs-pn-panels").prepend($("#sidebar-header"));
    $(".gjs-pn-panels").append($("#sidebar-bottom-buttons"));

    $('.btn.set-view').click(function (event) {
        window.editor.setDevice(event.currentTarget.dataset.view);
    });

    $("#toggle-sidebar").click(function() {
        $("#gjs").toggleClass('sidebar-collapsed');
        triggerEditorResize();
    });
    autoCollapseSidebar();

    window.editor.on('command:run:open-sm', function() {
        // Blocks with configured traits open Settings first, so synchronize
        // their style target when the user explicitly opens Style Manager.
        const selected = window.editor.getSelected();
        if (selected) {
            window.VihzhuoGrapesJS.componentInteractions.synchronizeStyleTarget(
                window.editor,
                selected,
                true
            );
        }

        // Keep GrapesJS's Selector Manager in a real custom property. Empty
        // sectors no longer render a reliable drop target in GrapesJS 0.23.
        window.VihzhuoGrapesJS.styleManager.mountSelectorManager(window.editor);
        setSidebarViewVisibility('.gjs-sm-sectors', true);
    });
    window.editor.on('command:stop:open-sm', function() {
        setSidebarViewVisibility('.gjs-sm-sectors', false);
    });
    window.editor.on('command:run:open-tm', function() {
        setSidebarViewVisibility('.gjs-trt-traits, .gjs-traits-cs', true);
    });
    window.editor.on('command:stop:open-tm', function() {
        setSidebarViewVisibility('.gjs-trt-traits, .gjs-traits-cs', false);
    });

    window.editor.on('block:drag:start', function(block) {
        autoCollapseSidebar();
    });

    function autoCollapseSidebar() {
        if ($(window).width() < 1000) {
            $("#gjs").addClass('sidebar-collapsed');
            triggerEditorResize();
        }
    }

    function triggerEditorResize() {
        window.editor.trigger('change:canvasOffset canvasScroll');
    }

    /**
     * GrapesJS 0.23 hides the contents of the Trait and Style commands when
     * stopped, but leaves their outer panel containers at full height. Hide
     * that outer view as well so inactive views cannot push the next one below
     * the sidebar's clipped viewport.
     */
    function setSidebarViewVisibility(contentSelector, visible) {
        const viewsContainer = document.querySelector('.gjs-pn-views-container');
        let view = viewsContainer?.querySelector(contentSelector);
        while (view && view.parentElement !== viewsContainer) {
            view = view.parentElement;
        }
        if (view) {
            view.style.display = visible ? 'block' : 'none';
        }
    }

    // prevent exiting page builder with backspace button
    let backspaceIsPressed = false;
    $(document).keydown(function(event) {
        if (event.which === 8) backspaceIsPressed = true;
    }).keyup(function(event) {
        if (event.which === 8) backspaceIsPressed = false;
    });
    $(window).on('beforeunload', function(event) {
        if (backspaceIsPressed) event.preventDefault();
    });
});

function addBlockSearch() {
    $(".gjs-blocks-cs").prepend($("#block-search"));
}

// listen to messages from iframe
window.addEventListener("message", onMessage, false);

function onMessage(event) {
    // if the page is loaded, remove loading element
    if (event.data === 'page-loaded') {
        $("#phpb-loading").addClass('loaded');
        addBlockSearch();
        window.isLoaded = true;
        $(window).trigger('pagebuilder-page-loaded');
    } else if (event.data === 'touch-start') {
        window.touchStart();
    }
}
