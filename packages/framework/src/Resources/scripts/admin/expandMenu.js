(function ($) {

    Shopsys = window.Shopsys || {};

    Shopsys.register.registerCallback(function ($container) {
        $container.filterAllNodes('.js-menu-switcher').on("click", function(){
            $('body').toggleClass("menu-collapsed");
        });
    });

})(jQuery);
