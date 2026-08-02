<?php

return [
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute must be a string.',
    'email' => 'The :attribute must be a valid email address.',
    'unique' => 'The :attribute has already been taken.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'array' => 'The :attribute must be a list.',
    'boolean' => 'The :attribute field must be true or false.',
    'distinct' => 'The :attribute field has a duplicate value.',
    'in' => 'The selected :attribute is invalid.',
    'min' => ['string' => 'The :attribute must be at least :min characters.', 'array' => 'The :attribute must contain at least :min items.'],
    'max' => ['string' => 'The :attribute may not be greater than :max characters.'],
    'password' => [
        'letters' => 'The :attribute must contain at least one letter.',
        'mixed' => 'The :attribute must contain at least one uppercase and one lowercase letter.',
        'numbers' => 'The :attribute must contain at least one number.',
        'symbols' => 'The :attribute must contain at least one symbol.',
        'uncompromised' => 'The :attribute has appeared in a data leak. Choose a different password.',
    ],
    'attributes' => [
        'name' => 'name', 'email' => 'email', 'password' => 'password',
        'current_password' => 'current password', 'roles' => 'roles', 'role' => 'role',
    ],
];
