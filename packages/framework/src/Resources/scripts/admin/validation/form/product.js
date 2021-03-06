(function ($) {
    $(document).ready(function () {
        var $productForm = $('form[name="product_form"]');
        $productForm.jsFormValidator({
            'groups': function () {

                var groups = [Shopsys.constant('\\Shopsys\\FrameworkBundle\\Form\\ValidationGroup::VALIDATION_GROUP_DEFAULT')];

                if ($('input[name="product_form[displayAvailabilityGroup][usingStock]"]:checked').val() === '1') {
                    groups.push(Shopsys.constant('\\Shopsys\\FrameworkBundle\\Form\\Admin\\Product\\ProductFormType::VALIDATION_GROUP_USING_STOCK'));
                    if ($('select[name="product_form[displayAvailabilityGroup][stockGroup][outOfStockAction]"]').val() === Shopsys.constant('\\Shopsys\\FrameworkBundle\\Model\\Product\\Product::OUT_OF_STOCK_ACTION_SET_ALTERNATE_AVAILABILITY')) {
                        groups.push(Shopsys.constant('\\Shopsys\\FrameworkBundle\\Form\\Admin\\Product\\ProductFormType::VALIDATION_GROUP_USING_STOCK_AND_ALTERNATE_AVAILABILITY'));
                    }
                } else {
                    groups.push(Shopsys.constant('\\Shopsys\\FrameworkBundle\\Form\\Admin\\Product\\ProductFormType::VALIDATION_GROUP_NOT_USING_STOCK'));
                }

                return groups;
            }
        });
    });
})(jQuery);
