<?php

return [
    'required' => 'The :attribute field is required.',
    'exists' => 'The selected :attribute is invalid.',
    'integer' => 'The :attribute field must be an integer.',
    'numeric' => 'The :attribute field must be a number.',
    'string' => 'The :attribute field must be a string.',
    'date' => 'The :attribute field must be a valid date.',
    'array' => 'The :attribute field must be an array.',
    'boolean' => 'The :attribute field must be true or false.',
    'min' => [
        'numeric' => 'The :attribute field must be at least :min.',
    ],
    'max' => [
        'numeric' => 'The :attribute field must not be greater than :max.',
    ],

    'custom' => [
        'plan_price_id' => [
            'required' => 'Please select a subscription plan and price.',
            'exists' => 'The selected plan or price is invalid.',
        ],
    ],

    'attributes' => [
        'plan_price_id' => 'plan price',
        'product_id' => 'product',
        'plan_id' => 'plan',
        'billing_interval' => 'billing interval',
        'quantity' => 'quantity',
        'start_date' => 'start date',
        'payment_terms' => 'payment terms',
        'installments_count' => 'installments count',
        'installment_due_day' => 'installment due day',
        'discount_jod' => 'discount amount',
        'discount_reason' => 'discount reason',
        'client_id' => 'client',
        'amount' => 'amount',
        'financial_account_id' => 'financial account',
        'category_id' => 'category',
        'vendor_id' => 'vendor',
        'funding_source_id' => 'funding source',
    ],
];
