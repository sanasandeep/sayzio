@include('user.links.partials.menu-editor-panes', [
    'mepTabs' => [
        'items' => ['icon'=>'fa-list','label'=>$businessItems],
        'design' => ['icon'=>'fa-palette','label'=>'How it looks'],
        'ordering' => ['icon'=>'fa-sliders-h','label'=>$businessWorkflow],
    ],
])
