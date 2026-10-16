<?php

// Public copies of the existing mobile policies. See docs/public-policies.md
// when updating the mobile text so both published versions stay aligned.
return json_decode(file_get_contents(resource_path('data/legal-policies.json')), true, 512, JSON_THROW_ON_ERROR);
