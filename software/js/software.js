(function( $ ) {

    $(document).ready(function () {

        // Close sample configs tip
        $(".close-tip .fa-times").click(function(event) {
            $(".sample-configs-tip").addClass("closed-config-tip");
            setTimeout(function() {
                $(".open-sample-config-tip").addClass("show");
            }, 1000);
            event.preventDefault();
        });
        // Open sample configs tip
        $(".open-sample-config-tip").click(function(event) {
            $(".sample-configs-tip").removeClass("closed-config-tip");
            $(this).removeClass('show');
            event.preventDefault();
        });

        // Show/Hide Additional Sample Configurations Details
        $(".more-about-config").click(function(event) {
            $(".more-sample-config").toggleClass("show");
            $(this).text(function(i, text){
                return text === "Less details about this configuration [-]" ? "More about this configuration [+]" : "Less details about this configuration [-]";
            })
            event.preventDefault();
        });

        $('ul.sample-configs-subnav li a').click(function(event) {
            $(this).position({
                left: "50%"
            });
            event.preventDefault();
        });

        // Scroll Sample Configuration subnav with arrows

        $('#config-right').click(function(event) {
            event.preventDefault();
            $('.sample-configs-slider').animate({
                scrollLeft: "+=300px"
            }, "fast");
            event.preventDefault();
        });
        $('#config-left').click(function(event) {
            event.preventDefault();
            $('.sample-configs-slider').animate({
                scrollLeft: "-=300px"
            }, "fast");
            event.preventDefault();
        });

        // Overview page, show/hide core projects
        $('#choose-compute').click(function(event) {
            $('#show-storage').removeClass('grey');
            $('#show-compute').addClass('grey');
            $('#compute-description').show();
            $('#storage-description').hide();
            $('#both-description').hide();
            event.preventDefault();
        });
        $('#choose-storage').click(function(event) {
            $('#show-compute').removeClass('grey');
            $('#show-storage').addClass('grey');
            $('#compute-description').hide();
            $('#storage-description').show();
            $('#both-description').hide();
            event.preventDefault();
        });
        $('#choose-both').click(function(event) {
            $('#show-storage').removeClass('grey');
            $('#show-compute').removeClass('grey');
            $('#compute-description').hide();
            $('#storage-description').hide();
            $('#both-description').show();
            event.preventDefault();
        });

        // Popovers
        $('[data-toggle="popover"]').popover()
    });

  // End of closure.
}( jQuery ));