<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'exists' => 'القيمة المحددة في :attribute غير صالحة.',
    'integer' => 'يجب أن يكون حقل :attribute رقماً صحيحاً.',
    'string' => 'يجب أن يكون حقل :attribute نصاً.',
    'date' => 'يجب أن يكون حقل :attribute تاريخاً صالحاً.',
    'min' => [
        'numeric' => 'يجب أن تكون قيمة :attribute على الأقل :min.',
    ],
    'max' => [
        'numeric' => 'يجب ألا تتجاوز قيمة :attribute :max.',
    ],

    'custom' => [
        'plan_price_id' => [
            'required' => 'يرجى اختيار خطة وسعر الاشتراك.',
            'exists' => 'السعر أو الخطة المختارة غير صالحة.',
        ],
    ],

    'attributes' => [
        'plan_price_id' => 'سعر الخطة',
        'product_id' => 'المنتج',
        'plan_id' => 'الخطة',
        'billing_interval' => 'دورية الفوترة',
        'quantity' => 'الكمية',
        'start_date' => 'تاريخ البدء',
        'payment_terms' => 'شروط الدفع',
        'installments_count' => 'عدد الأقساط',
        'installment_due_day' => 'يوم استحقاق القسط',
        'discount_jod' => 'قيمة الخصم',
        'discount_reason' => 'سبب الخصم',
        'client_id' => 'العميل',
        'amount' => 'المبلغ',
        'financial_account_id' => 'الحساب المالي',
        'category_id' => 'التصنيف',
        'vendor_id' => 'المورد',
        'funding_source_id' => 'مصدر التمويل',
    ],
];
