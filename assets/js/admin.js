jQuery(document).ready(function($) {
    function validateMergeTag(input) {
        var $input = $(input);
        var $wrapper = $input.closest(".text-field-wrapper");
        var $validation = $wrapper.find(".merge-tag-validation");
        var value = $input.val();
        
        if (value && !value.includes("{stock}")) {
            $validation.html("This field must contain the {stock} merge tag").show();
            $input.css("border-color", "#d63638");
            return false;
        } else {
            $validation.hide();
            $input.css("border-color", "");
            return true;
        }
    }
    
    // Validate on input change
    $(".requires-merge-tag").on("input", function() {
        validateMergeTag(this);
    });
    
    // Initial validation
    $(".requires-merge-tag").each(function() {
        validateMergeTag(this);
    });
    
    // Validate before form submission
    $("form").on("submit", function(e) {
        var isValid = true;
        $(".requires-merge-tag").each(function() {
            if (!validateMergeTag(this)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert("Please fix the merge tag errors before saving.");
        }
    });
});