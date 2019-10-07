(function($) {
  "use strict"; // Start of use strict

  // Toggle the side navigation
  $("#sidebarToggle").on('click', function(e) {
    e.preventDefault();
    $("body").toggleClass("sidebar-toggled");
    $(".sidebar").toggleClass("toggled");
  });

  // Prevent the content wrapper from scrolling when the fixed side navigation hovered over
  $('body.fixed-nav .sidebar').on('mousewheel DOMMouseScroll wheel', function(e) {
    if ($(window).width() > 768) {
      var e0 = e.originalEvent,
        delta = e0.wheelDelta || -e0.detail;
      this.scrollTop += (delta < 0 ? 1 : -1) * 30;
      e.preventDefault();
    }
  });

  // Scroll to top button appear
  $(document).on('scroll', function() {
    var scrollDistance = $(this).scrollTop();
    if (scrollDistance > 100) {
      $('.scroll-to-top').fadeIn();
    } else {
      $('.scroll-to-top').fadeOut();
    }
  });

  // Smooth scrolling using jQuery easing
  $(document).on('click', 'a.scroll-to-top', function(event) {
    var $anchor = $(this);
    $('html, body').stop().animate({
      scrollTop: ($($anchor.attr('href')).offset().top)
    }, 1000, 'easeInOutExpo');
    event.preventDefault();
  });

})(jQuery); // End of use strict

// Calculator JS
var Controller = (function(){
    //Global Variable
    var val, stackArr, total;

    val = '';
    stackArr = [];
    total = 0;

    //------------------------------------------------------------------
    //DOM Element Ref.
    var DOM = {
        summery: document.querySelector('.calculator__summery'),
        stack: document.querySelector('.calculator__stack')
    }
    //------------------------------------------------------------------
    //function : Clear UI
    var clearUI = function(){
        total = 0;
        stackArr = [];
        DOM.summery.textContent = "0";
        DOM.stack.textContent = "0";
    }
    //------------------------------------------------------------------
    //function : format Number (turn 5000 --> 5,000.00)
    var formatNum = function(num){

        num = Math.abs(num);
        num = num.toFixed(2);
        var numSplit = num.split(".");
        ;
        var int = parseInt(numSplit[0]);
        var dec = numSplit[1];

        return int.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,') + '.' + dec;
    }

    //------------------------------------------------------------------
    //Main Function : check operator on click
    var checkOps = function(){
        if(val){

            // btn -> " = " btn
            if(val === "sum"){
                var merge = document.querySelector('.calculator__stack').innerHTML;
                total = eval(merge);
                DOM.stack.textContent = "0";
                DOM.summery.textContent = formatNum(total);
                stackArr = [];
                stackArr.push(total);

            // btn -> " del "
            }else if(val === "del"){
                stackArr.pop();
                stackArr.length === 0 ?  DOM.stack.textContent = "0" : DOM.stack.textContent = stackArr.join('');

            // btn -> " C "
            }else if(val === "clear"){
                clearUI();

            // btn -> " CE "
            }else if(val === "clear-entry"){
                DOM.summery.textContent = formatNum(total);
                DOM.stack.textContent = "0";
                stackArr = [];
                stackArr.push(total);

            // btn -> (Number , ". [dot]")
            }else{
                stackArr.push(val);
                DOM.summery.textContent = "0";
                DOM.stack.textContent = stackArr.join('');
            }
        }
    }

    //------------------------------------------------------------------
    // EventLister : Setup
    var getEvent = function(){
        document.addEventListener('click', function(e){
            // 1. get Value form html
            val = e.target.getAttribute('value');
            // 2. Call checkOps method
            checkOps();
        });
    }

    //------------------------------------------------------------------

    return{
        init: function(){
            //1. Cennect with Script.js
            console.log("Script.js : connecting... ");
            //2. Clear UI prepare for process
            clearUI();
            //3. Call method : Main System
            getEvent();
        }
    }
})();

//Start Program
Controller.init();
