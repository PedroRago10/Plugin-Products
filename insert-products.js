function callVariations(product, version, name, document, quantity) {
    return jQuery.ajax({
        type: 'GET',
        url: ajaxurl,
        data: {
            action: 'call_variations',
            product: product,
            version: version,
            name: name,
            quantities: quantity,
            document: document,
        },
        success: function(response) {
        },
        error: function(error) {
            // Handle errors
        }
    });
}
function finishAll(urlDocument) {
    jQuery(".btnDownload").attr("href", 'https://' + urlDocument);
    jQuery(".btnDownload").fadeIn();
    jQuery(".feedback-variations-label").text('');
    jQuery(".feedback-variations b").text('');
    type = false;
    totalVariations = 0;
    position = 0;
    productData = '';
    product = '';
    partsString = '';
    version = '';
    name = '';
    urlDocument = '';
    jQuery(".options").fadeOut();
    jQuery(".options select").remove();

    jQuery(".quantities").fadeOut();
    jQuery(".quantities select option").remove();

    jQuery(".products").fadeOut();
    jQuery(".products select").fadeOut();
    jQuery(".products select").val("")
    jQuery(".form-check-input").prop("checked", false);
    isLoading(false);

    jQuery("#insert-products-form input, #insert-products-form select").prop('disabled', false);
    jQuery("#insert-products-form input, #insert-products-form select").removeClass('disabled');

    jQuery("#insert-products-form input, #insert-products-form select").removeAttr('disabled');
}
function isLoading(toggle, name = '')
{
    if(toggle) {
        jQuery("#insert-products-form").addClass("insearch");
        jQuery("#insert-products-form input, #insert-products-form select, #insert-products-form button").prop('disabled', true);
        jQuery("#insert-products-form input, #insert-products-form select, #insert-products-form button").addClass('disabled');
        jQuery(".title-loading strong").text(name);
        jQuery(".loading").fadeIn();
    }else{
        jQuery("#insert-products-form input, #insert-products-form select").prop('disabled', false);
        jQuery("#insert-products-form input, #insert-products-form select").removeClass('disabled');
        jQuery(".loading").fadeOut();
        jQuery(".title-loading strong").text('');
        jQuery("#insert-products-form").removeClass("insearch");
    }

}
function makeGroupedRequests(product, version, name, urlDocument, quantities, counter) {
    const batchSize = 5; // Tamanho máximo de cada grupo
    const groups = [];
    for (let i = 0; i < quantities.length; i += batchSize) {
        groups.push(quantities.slice(i, i + batchSize));
    }

    let currentIndex = 0;
    function makeRequests() {
        if (currentIndex < groups.length) {
            const group = groups[currentIndex];
            const requests = group.map(quantity =>
                callVariations(product, version, name, urlDocument, quantity)
            );

            Promise.all(requests)
                .then(() => {
                    currentIndex++;
                    counter += group.length;
                    // updateFeedbackCounter(counter); // Atualiza o contador de requisições concluídas
                    makeRequests(); // Chama o próximo grupo

                    if (currentIndex === groups.length) {
                        // Todas as requisições foram concluídas
                        finishAll(urlDocument);
                    }
                })
                .catch(error => {
                    console.error('Error in grouped requests:', error);
                });
        }
    }

    makeRequests();
}
function updateFeedbackTotal(total) {
    jQuery(".feedback-variations span").text(total);
}

function updateFeedbackCounter(counter) {
    jQuery(".feedback-variations b").text(counter);
}


jQuery(document).ready(function($) {

    jQuery(document).off("click", ".btnDownload");
    jQuery(document).off("click", ".btnDownload", function(){
        setTimeout(function(){
            window.location.href = window.location.href;
        }, 2000)
    })
    var type = false;
    var totalVariations = 0;  
    var productData = '';
    var product = '';
    var partsString = '';
    var version = '';
    var name  = '';
    var urlDocument = '';

    var url = "action=" + encodeURIComponent("insert_products_submit_form") + "&product=" + encodeURIComponent(product) + "&version=" + encodeURIComponent(version) + "&name=" + encodeURIComponent(name) + "&";
    var formData = url + $("#insert-products-form :input").not('[name="builder_type"]').not('[name="builder_name"]').not('[name="action"]').not('[name="_wpnonce"]').not('[name="_wp_http_referer"]').serialize();
    $(".btnDownload").on("click", function() {
        $(this).fadeOut();
    });

    

    
    $(".btnSubmitScrap").on("click", function(event) {
        variations = [];
        $(".btnDownload").fadeOut();
        $(".btnSubmitScrap").text("Performing scraping");
    
        product = $(".products select[data-category='" + type + "']").val();
        partsString = product.split("=");
        version = parseInt(partsString[partsString.length - 1]);
        name = $(".products select[data-category='" + type + "'] option:selected").text();
    
        url = "action=" + encodeURIComponent("insert_products_submit_form") + "&product=" + encodeURIComponent(product) + "&version=" + encodeURIComponent(version) + "&name=" + encodeURIComponent(name) + "&";
        formData = url + $("#insert-products-form :input").not('[name="builder_type"]').not('[name="builder_name"]').not('[name="action"]').not('[name="_wpnonce"]').not('[name="_wp_http_referer"]').serialize();
        isLoading(true, name);
    
        const maxRetries = 3;
        let retries = 0;
    
        function performRequest(total, counter) {
            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: formData,
                success: function(response) {
                    $(".btnSubmitScrap").text("Search by Product");
                    $(".feedback .feedback-product").text('Product found')
    
                    response = response.data;
                    urlDocument = response.file_url
                    if (response.status === 'success' && response.file_url) {
                        $(".feedback-variations-label").text('Generating possible product variations')     

                        productData = response.data;
                        const quantities = productData.compatibleQuantities;

                        makeGroupedRequests(product, version, name, urlDocument, quantities, counter);
                    }
                },
                error: function(error) {
                     if (retries < maxRetries && error.status === 500) {
                        retries++;
                        performRequest(total, counter); // Tentar novamente em caso de erro 500
                    } else {
                        isLoading(false);
                        alert(error);
                    }
                }
            });
        }
    
        performRequest(0, 0);
    });

    $(".form-check-input").on("click", function() { 
        var value = $(this).val();
        
        $(".products select").hide();
        $(".products").find("select[data-category='" + value + "']").fadeIn();
        $(".products").fadeIn();
        type = value;
    });

    jQuery(".products select").on("change", function() {
      if(jQuery(this).val() != '') {
        jQuery("#insert-products-form button").prop('disabled', true);
        jQuery("#insert-products-form button").addClass('disabled');

        var product = $(".products").find("select[data-category='" + type + "']").val();
        var partsString = product.split("=");
        var version = parseInt(partsString[partsString.length - 1]);
        var name = $(".products select[data-category='" + type + "'] option:selected").text();
        $(".options .options-selects").find("div").remove();
        $(".quantities select option").remove();
        $(".btnSubmitScrap").attr("disabled", true);
        $(".btnSubmitScrap").addClass("disabled");
        $(".btnSubmitScrap").text("Searching product information");
        $.ajax({
            type: 'get',
            url: ajaxurl,
            data: { action: 'products_get_options', product: product, version: version, name: name },
            success: function(response) {
                var compatibleOptions = '';
                
                if (typeof response.data.compatibleOptions === 'object') {
                    for (var key in response.data.compatibleOptions) {
                        if (Array.isArray(response.data.compatibleOptions[key]) && key != 'Connected Cards') {
                            var options = '';
                            response.data.compatibleOptions[key].forEach(function(item) {
                                options += '<option value="' + item + '">' + item + '</option>';
                            });
                            
                            var box = '<div style="display: flex; flex-direction: column;margin-right: 1em;margin-bottom: 1em;">' +
                                      '<h4 style="margin-top: 0; margin-bottom: 1.5em;">' + key + ':</h4>' +
                                      '<select style="padding: 0.3em 1em;" name="option[' + key + ']">' +
                                      '<option value=""></option>' +
                                      options +
                                      '</select></div>';
                            
                            $('.options .options-selects').append(box);
                        }
                    }
                } else {
                    // Handle the case when compatibleOptions is not an object
                    console.error('compatibleOptions is not an object:', response.data.compatibleOptions);
                }

                $(".quantities select option").remove();
                if (response.data.compatibleQuantities) {
                    for (var key in response.data.compatibleQuantities) {
                        if (!$(".qa" + response.data.compatibleQuantities[key]).length) {
                            var quantities = '<option value="' + response.data.compatibleQuantities[key] + '" class="qa' + response.data.compatibleQuantities[key] + '">' + response.data.compatibleQuantities[key] + '</option>';
                            $(".quantities select").append(quantities);
                        }
                    }
                    
                }
              
                $(".btnSubmitScrap").removeAttr("disabled");
                $(".btnSubmitScrap").removeClass("disabled");
                $(".btnSubmitScrap").text("Search by product");
            },
            error: function(error) {
                // Handle errors
                alert('An error occurred while submitting the form.');
            }
        });
      }
    });
});
