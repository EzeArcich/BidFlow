<?php

return [
    'accepted' => 'Debés aceptar :attribute.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'array' => 'El campo :attribute no tiene un formato válido.',
    'date' => 'Ingresá una fecha válida en :attribute.',
    'distinct' => 'No se puede repetir :attribute en la misma selección.',
    'email' => 'Ingresá un correo electrónico válido.',
    'in' => 'Seleccioná una opción válida para :attribute.',
    'enum' => 'Seleccioná una opción válida para :attribute.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'missing' => 'No está permitido enviar el campo :attribute.',
    'regex' => 'El campo :attribute no tiene un formato válido.',
    'required' => 'Completá el campo :attribute.',
    'string' => 'El campo :attribute debe ser un texto.',
    'ulid' => 'El identificador de :attribute no es válido.',
    'min' => ['numeric' => ':attribute debe ser al menos :min.', 'string' => ':attribute debe tener al menos :min caracteres.', 'array' => 'Seleccioná al menos :min elemento en :attribute.'],
    'max' => ['numeric' => ':attribute no puede superar :max.', 'string' => ':attribute no puede superar :max caracteres.', 'array' => ':attribute no puede tener más de :max elementos.'],
    'attributes' => [
        'email' => 'correo electrónico', 'password' => 'contraseña', 'title' => 'título',
        'description' => 'descripción', 'type' => 'categoría', 'deadline_at' => 'fecha límite',
        'deadline_local' => 'fecha y hora límite', 'conditions_text' => 'condiciones',
        'quantity' => 'cantidad', 'unit' => 'unidad', 'position' => 'posición',
        'specifications_text' => 'especificaciones', 'reference_code' => 'referencia',
        'organization_id' => 'organización', 'invitations' => 'proveedores',
        'invitations.*.supplier_id' => 'proveedor', 'invitations.*.contact_id' => 'contacto',
    ],
];
