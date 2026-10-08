<?php

return [
    // Department JSON and annual ZIP exports can exceed Livewire's 12 MB default.
    // PHP and the web server must also permit the configured upload size.
    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:51200'],
    ],
];
