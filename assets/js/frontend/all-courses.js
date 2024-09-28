(function($) {
  if($('#min_credits').length) {
    window.onload = function(){
        slideOne();
        slideTwo();
    }

    $(document).on('input','#min-credits-slider', function() {
        slideOne();
    });
    $(document).on('input','#max-credits-slider', function() {
        slideTwo();
    });
    var sliderOne = document.getElementById("min-credits-slider");
    var sliderTwo = document.getElementById("max-credits-slider");
    var displayValOne = document.getElementById("range1");
    var displayValTwo = document.getElementById("range2");
    var minGap = 0;
    var sliderTrack = document.querySelector(".flms-slider-track");
    var sliderMinValue = document.getElementById("min-credits-slider").min;
    var sliderMaxValue = document.getElementById("min-credits-slider").max;
    
    function slideOne(){
        if(parseInt(sliderTwo.value) - parseInt(sliderOne.value) <= minGap){
            sliderOne.value = parseInt(sliderTwo.value) - minGap;
        }
        displayValOne.textContent = sliderOne.value;
        fillColor();
    }
    function slideTwo(){
        if(parseInt(sliderTwo.value) - parseInt(sliderOne.value) <= minGap){
            sliderTwo.value = parseInt(sliderOne.value) + minGap;
        }
        displayValTwo.textContent = sliderTwo.value;
        fillColor();
    }

    function fillColor(){
        if(sliderMinValue > 0) {
          percent1 = Math.ceil(((sliderOne.value - sliderMinValue) / sliderMaxValue) * 100);
          //console.log((sliderOne.value / sliderMaxValue));
          percent1 = Math.floor((sliderOne.value / sliderMaxValue) * 100 - sliderMinValue);
          //console.log(percent1);

        } else {
          percent1 = (sliderOne.value / sliderMaxValue) * 100;
        }
        
        
        percent2 = Math.floor((sliderTwo.value / sliderMaxValue) * 100 - sliderMinValue);
        sliderTrack.style.background = `linear-gradient(to right, ${flms_all_courses.background_color} ${percent1}% , ${flms_all_courses.primary_color} ${percent1}% , ${flms_all_courses.primary_color} ${percent2}%, ${flms_all_courses.background_color} ${percent2}%)`;
    }

    $(document).on('change','#min_credits_select', function() {
      var val = $(this).val();
      $('#min_credits').val(val);
      $('#min-credits-slider').val(val);
      fillColor();
    });
    $(document).on('change','#max_credits_select', function() {
      var val = $(this).val();
      $('#max_credits').val(val);
      $('#max-credits-slider').val(val);
      fillColor();
    });
    $(document).on('change','#min-credits-slider', function() {
      var val = $(this).val();
      $('#min_credits').val(val);
      $('#min_credits_select').val(val);
      fillColor();
    });
    $(document).on('change','#max-credits-slider', function() {
      var val = $(this).val();
      $('#max_credits').val(val);
      $('#max_credits_select').val(val);
      fillColor();
    });
  }

  /*$('.select2').select2({
        width: 'style',
  });*/
  
  $('form').on("reset",function(e){   
    /*var $this = $(this);
    $('.flms-course-filters input[type="text"]').val('');
    $('.flms-course-filters [type=checkbox]').prop("checked", false);
    //reset select 2
    $("select.select2").select2('val', '0'); // clear out values selected
    //reset min/max credits
    if($('#min_credits').length) {
        var resetVal = $('#min_credits').attr('data-reset');
        $('#min_credits,#min_credits_select,#min-credits-slider').val(resetVal);
        var resetValMax = $('#max_credits').attr('data-reset');
        $('#max_credits,#max_credits_select,#max-credits-slider').val(resetValMax);
    }
    
    $this.submit();*/
    window.location = flms_all_courses.permalink;
  });

  $(document).on('click','#flms-toggle-course-filters', function() {
        var filters = $('#flms-course-filters');
        var $this = $(this);
        $this.toggleClass('flms-is-active');
        var text = $this.find('.text').text();
        var toggletext = $this.attr('data-toggle-text');
        if (!filters.hasClass('flms-filters-hidden') && !filters.hasClass('flms-auto-height')) {
            filters.animate({ height: filters.prop('scrollHeight') }, 100, 'linear', function() {
              $this.find('.text').text(toggletext);
              $this.attr('data-toggle-text', text);
            });
            filters.addClass('flms-filters-hidden');
        } else {
            filters.removeClass('flms-filters-hidden');
            filters.animate({ height: 0 }, 100, 'linear', function() {
              $this.find('.text').text(toggletext);
              $this.attr('data-toggle-text', text);
              filters.removeClass('flms-auto-height')
            });
        }
  });

})( jQuery );