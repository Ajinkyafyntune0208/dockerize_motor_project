@extends('admin_lte.layout.app', ['activePage' => 'template', 'titlePage' => __('Template')])
@section('content')
    <!-- general form elements disabled -->
    <a href="{{ route('admin.template.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
    <style>
        .ck-content {
            height: 200px;
        }

        .dropbtn {
            background-color: #3498DB;
            color: white;
            padding: 16px;
            font-size: 16px;
            border: none;
            cursor: pointer;
        }

        .dropbtn:hover,
        .dropbtn:focus {
            background-color: #2980B9;
        }

        #dropdown {
            position: relative;
            display: flex;
            margin-top: 20px;
        }

        .dropdown-content {
            display: none;
            position: relative;
            background-color: #f1f1f1;
            min-width: 160px;
            overflow: auto;
            box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
            z-index: 1;
        }

        .dropdown-content p {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            cursor: pointer;
        }

        .dropdown p:hover {
            background-color: #ddd;
        }

        .show {
            display: block;
        }
    </style>
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Edit Template</h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <form action="{{ route('admin.template.update', $template) }}" method="POST">@csrf @method('PUT')
                <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                    <label for="title" class="col-sm-2 col-form-label required">Title</label>
                    <div class="col-sm-10">
                        <input type="text" name="title" class="form-control" id="title" placeholder="Title"
                            value="{{ $template->title }}">
                        @error('title')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                    <label for="alias" class="col-sm-2 col-form-label required">Alias</label>
                    <div class="col-sm-10">
                        <div class="dropdown input-group">
                            <span class="input-group-text" id="dropbtn" onclick="myFunction()"
                                style="cursor: pointer;">↓</span>
                            <input type="text" class="form-control" placeholder="Alias" aria-label="alias" id="alias"
                                name="alias" readonly value="{{ $template->alias }}">
                            <div id="myDropdown" class="dropdown-content w-100" style=" height: 20rem;">
                                @foreach ($alias as $key => $value)
                                    <div style="display: flex;">
                                        <div class="w-100">
                                            <p style="font-size: 0.9rem;"
                                                onclick="addOption('{{ $value['display_name'] }}')">
                                                {{ $value['display_name'] }}</p>
                                        </div>
                                        <button type="button" id="edit" class="btn text-success"
                                            onclick="editOption('{{ $value['display_name'] }}')"><i
                                                class="fa fa-edit"></i></button>
                                        <button type="button" id="delete" class="btn text-danger"
                                            onclick="deleteOption('{{ $value['display_name'] }}')"><i
                                                class="fa fa-trash"></i></button>
                                    </div>
                                @endforeach
                                <p onclick="addOption('global_footer')">Global Footer</p>
                                <p onclick="addOption('global_header')">Global Header</p>
                                <p onclick="addOption('add')">Add Option</p>
                            </div>
                        </div>
                        <a href="#" id="callAuto" data-bs-toggle="modal" data-bs-target="#exampleModal"
                            class="text-danger" data-logo-path ="" data-logo-name="" style="display: none">Add
                            Option</a>
                        @error('alias')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                    <label for="alias" class="col-sm-2 col-form-label required">Communication<br>
                        Type</label>
                    <div class="col-sm-10">
                        <input type="text" name="communication_type_email" class="form-control" id="communication_email"
                            readonly>
                        <select name="communication_type" data-style="btn-sm btn-primary" data-actions-box="true"
                            class="selectpicker w-100 communication_type_hide" data-live-search="true"
                            id="communication_type">
                            <option value="">Nothing Selected</option>
                            @foreach ($types as $key => $value)
                                <option
                                    {{ old('value', $template->communication_type) == $value['value'] ? 'selected' : '' }}
                                    value="{{ $value['value'] }}">{{ $value['value'] }}</option>
                            @endforeach
                        </select>
                        @error('communication_type')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;"
                    id="email_subject_field">
                    <label for="subject" class="col-sm-2 col-form-label required">Subject</label>
                    <div class="col-sm-10">
                        <input type="text" name="subject" class="form-control" id="subject" placeholder="Subject"
                            value="{{ old('subject', $template->subject) }}">
                        @error('subject')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;"
                    id='message_type_field'>
                    <label for="message_type" class="col-sm-2 col-form-label required">Message
                        type</label>
                    <div class="col-sm-10">
                        <select name="message_type" data-style="btn-sm btn-primary" data-actions-box="true"
                            class="selectpicker w-100" data-live-search="true" id="message_type">
                            <option value="">Nothing Selected</option>

                            <option value="Plane Text"
                                {{ old('value', $template->message_type) == $template->message_type ? 'selected' : '' }}>
                                Plane
                                text</option>
                            <option value="Image"
                                {{ old('value', $template->message_type) == $template->message_type ? 'selected' : '' }}>
                                Image
                            </option>
                        </select>
                        @error('message_type')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;"
                    id='global_header_field'>
                    <label for="global_header" class="col-sm-2 col-form-label required">Global
                        Header</label>
                    <div class="col-sm-10">

                        <textarea name="global_header" id="global_header" placeholder="Global Header" autocomplete="off"
                            style="height:200px;width:100%">{{ old('global_header', $template->global_header) }}</textarea>
                        @error('global_header')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                    <label for="email" class="col-sm-2 col-form-label required">Content</label>
                    <div class="col-sm-10">
                        <textarea name="content" id="content" placeholder="Content" autocomplete="off" style="height:200px;width:100%">{{ old('content', $template->content) }}</textarea>
                        @error('content')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;"
                    id='footer_field'>
                    <label for="footer" class="col-sm-2 col-form-label required">Global Footer</label>
                    <div class="col-sm-10">
                        <textarea name="footer" id="footer" placeholder="Global footer" autocomplete="off"
                            style="height:200px;width:100%">{{ old('global_footer', $template->footer) }}</textarea>
                        @error('footer')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div id="email_field">
                    <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                        <label for="to" class="col-sm-2 col-form-label">To Email</label>
                        <div class="col-sm-10">
                            <input type="text" name="to" class="form-control" id="to"
                                placeholder="To Email" value="{{ old('to', $template->to) }}">
                        </div>
                    </div>
                    <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                        <label for="cc" class="col-sm-2 col-form-label">Cc</label>
                        <div class="col-sm-10">
                            <input type="text" name="cc" class="form-control" id="cc" placeholder="Cc"
                                value="{{ old('cc', $template->cc) }}">
                        </div>
                    </div>
                    <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                        <label for="bcc" class="col-sm-2 col-form-label">Bcc</label>
                        <div class="col-sm-10">
                            <input type="text" name="bcc" class="form-control" id="bcc" placeholder="Bcc"
                                value="{{ old('bcc', $template->bcc) }}">
                        </div>
                    </div>
                </div>
                <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                    <label for="status" class="col-sm-2 col-form-label required">Status</label>
                    <div class="col-sm-10">
                        <select class="form-control select2" name="status">
                            <option value="active"
                                {{ strtolower(trim($template->status)) == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive"
                                {{ strtolower(trim($template->status)) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('status')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <button type="submit" class="btn btn-primary me-2">Submit</button>
            </form>
        </div>
        <div class="modal fade" id="exampleModal" tabindex="1" aria-labelledby="exampleModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <form action="{{ route('admin.template.store') }}" enctype="multipart/form-data" method="post"
                    id="update-logo">@csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel1"></h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <div class="form-group row"
                                    style="display: flex; flex-direction: row; align-items: center;">
                                    <label for="option_name" class="col-sm-4 col-form-label">Option Name</label>
                                    <div class="col-sm-6">
                                        <input type="text" name="option_name" class="form-control" id="option_name"
                                            placeholder="Option Name">
                                    </div>
                                    <div class="col-sm-6" style="display: none">
                                        <input type="text" name="option_edit" class="form-control" id="option_edit"
                                            placeholder="option_edit">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection('content')
@section('scripts')
    <script src="https://cdn.ckeditor.com/ckeditor5/41.3.1/super-build/ckeditor.js"></script>
    <script>
        var autocomplete = [];
        var autocomplete = "{{ $autocomplete }}";
        autocomplete = autocomplete.split(',');
        autocomplete = autocomplete.map(function(single) {
            return '@' + single;
        });
        $('#communication_email').hide();


        $(document).ready(function() {

            $('#alias').change(function() {
                $.ajax({
                    "url": "{{ url('admin/template-add') }}",
                    "method": "post",
                    "data": {
                        "get_name": true,
                        "_token": "{{ csrf_token() }}",
                        "alias": $(this).val()
                    },
                    "success": function(result) {
                        autocomplete = result.data;
                        autocomplete = autocomplete.map(function(single) {
                            return '@' + single;
                        });
                    }
                });
            });
            let alias = $('#alias').val();
            if (alias == 'global_header') {
                $('#alias').val('Global Header');
            } else if (alias == 'global_footer') {
                $('#alias').val('Global Footer');
            }

            let dc = document.querySelector('#communication_type').value;
            let changeImg = document.querySelector('#message_type').value;
            communicationTypeOption(dc);
            if (dc == 'whatsapp') {
                if (changeImg == "Image") {
                    if (!document.querySelector('.ck-sticky-panel')) {
                        ckeditor(autocomplete);
                    } else {
                        $('.ck-sticky-panel').show();
                    }
                } else {
                    $('.ck-sticky-panel').hide();
                }
            }
        });
        $('.ck-sticky-panel').hide();
        $('#global_header_field').hide();
        $('#footer_field').hide();
        $('#message_type_field').hide();
        $('#email_field').hide();
        $('#email_subject_field').hide();
        $('#communication_email').hide();

        function ckeditor(autocomplete) {

            CKEDITOR.ClassicEditor.create(document.getElementById("content"), {
                toolbar: {
                    items: [
                        'bold', 'italic', '|', 'bulletedList', 'numberedList', 'todoList', '|',
                        'fontSize', 'fontFamily', 'fontColor', 'link', 'uploadImage', 'insertTable'
                    ],
                    shouldNotGroupWhenFull: true
                },
                mention: {
                    feeds: [{
                        marker: '@',
                        feed: autocomplete,
                        minimumCharacters: 0
                    }]
                },
                removePlugins: [
                    'ExportPdf',
                    'ExportWord',
                    'AIAssistant',
                    'CKBox',
                    'CKFinder',
                    'EasyImage',
                    'MultiLevelList',
                    'RealTimeCollaborativeComments',
                    'RealTimeCollaborativeTrackChanges',
                    'RealTimeCollaborativeRevisionHistory',
                    'PresenceList',
                    'Comments',
                    'TrackChanges',
                    'TrackChangesData',
                    'RevisionHistory',
                    'Pagination',
                    'WProofreader',
                    'MathType',
                    'SlashCommand',
                    'Template',
                    'DocumentOutline',
                    'FormatPainter',
                    'TableOfContents',
                    'PasteFromOfficeEnhanced',
                    'CaseChange'
                ]
            });
            CKEDITOR.ClassicEditor.create(document.getElementById("footer"), {
                toolbar: {
                    items: [
                        'bold', 'italic', '|', 'bulletedList', 'numberedList', 'todoList', '|', 'fontSize',
                        'fontFamily', 'fontColor', 'link', 'uploadImage', 'insertTable'
                    ],
                    shouldNotGroupWhenFull: true
                },
                mention: {
                    feeds: [{
                        marker: '@',
                        feed: autocomplete,
                        minimumCharacters: 0
                    }]
                },
                removePlugins: [
                    'ExportPdf',
                    'ExportWord',
                    'AIAssistant',
                    'CKBox',
                    'CKFinder',
                    'EasyImage',
                    'MultiLevelList',
                    'RealTimeCollaborativeComments',
                    'RealTimeCollaborativeTrackChanges',
                    'RealTimeCollaborativeRevisionHistory',
                    'PresenceList',
                    'Comments',
                    'TrackChanges',
                    'TrackChangesData',
                    'RevisionHistory',
                    'Pagination',
                    'WProofreader',
                    'MathType',
                    'SlashCommand',
                    'Template',
                    'DocumentOutline',
                    'FormatPainter',
                    'TableOfContents',
                    'PasteFromOfficeEnhanced',
                    'CaseChange'
                ]
            });
            CKEDITOR.ClassicEditor.create(document.getElementById("global_header"), {
                toolbar: {
                    items: [
                        'bold', 'italic', '|', 'bulletedList', 'numberedList', 'todoList', '|', 'fontSize',
                        'fontFamily', 'fontColor', 'link', 'uploadImage', 'insertTable'
                    ],
                    shouldNotGroupWhenFull: true
                },
                mention: {
                    feeds: [{
                        marker: '@',
                        feed: autocomplete,
                        minimumCharacters: 0
                    }]
                },
                removePlugins: [
                    'ExportPdf',
                    'ExportWord',
                    'AIAssistant',
                    'CKBox',
                    'CKFinder',
                    'EasyImage',
                    'MultiLevelList',
                    'RealTimeCollaborativeComments',
                    'RealTimeCollaborativeTrackChanges',
                    'RealTimeCollaborativeRevisionHistory',
                    'PresenceList',
                    'Comments',
                    'TrackChanges',
                    'TrackChangesData',
                    'RevisionHistory',
                    'Pagination',
                    'WProofreader',
                    'MathType',
                    'SlashCommand',
                    'Template',
                    'DocumentOutline',
                    'FormatPainter',
                    'TableOfContents',
                    'PasteFromOfficeEnhanced',
                    'CaseChange'
                ]
            });
        }

        function addOption(alias) {
            if (alias == 'global_header') {
                $('#alias').val('Global Header');
            } else if (alias == 'global_footer') {
                $('#alias').val('Global Footer');
            } else if (alias != 'add') {
                $('#alias').val(alias);
            }
            aliasOption(alias);
        }

        function aliasOption(alias) {
            if (alias == 'global_header' || alias == 'global_footer') {
                $('#global_header_field').hide();
                $('#footer_field').hide();
                $('#message_type_field').hide();
                $('#email_field').hide();
                $('#email_subject_field').hide();
                $('#smsoption').hide();
                $('.communication_type_hide').hide();
                $('#communication_email').show();
                $('#communication_email').val('email');
            }
            if (alias == 'global_header') {
                $('.communication_type_hide').hide();
                $('#communication_email').show();
                $('#communication_email').val('email');
                if (!document.querySelector('.ck-sticky-panel')) {
                    ckeditor(autocomplete);
                } else {
                    $('.ck-sticky-panel').show();
                }
            } else if (alias == 'global_footer') {
                $('.communication_type_hide').hide();
                $('#communication_email').show();
                $('#communication_email').val('email');
                if (!document.querySelector('.ck-sticky-panel')) {
                    ckeditor(autocomplete);
                } else {
                    $('.ck-sticky-panel').show();
                }
            } else if (alias == "add") {
                let callAuto = document.getElementById('callAuto');
                $('#exampleModalLabel1').text('Option Add');
                callAuto.click();
            } else {
                $('.communication_type_hide').show();
                $('#communication_email').hide();
                $('#title').val('');
                $("#content").html(" ");
                communicationTypeOption($('#communication_type').val());
            }
        }


        $('#exampleModal').on('hide.bs.modal', function() {
            $('.selectpicker').selectpicker('val', '');
        });

        $('#communication_type').change(function() {
            communicationTypeOption($(this).val());
        });

        function communicationTypeOption(communication_type) {
            var currentValue = $('#alias').val();
            var newValue = currentValue.replace(/ /g, '_');
            var lowerCaseValue = newValue.toLowerCase();
            if (communication_type == "whatsapp") {
                $('#message_type_field').show();
                $('#global_header_field').hide();
                $('#footer_field').hide();
                $('#email_subject_field').hide();
                $('#email_field').hide();
                $('#message_type').change(function() {
                    if ($(this).val() == "Image") {
                        if (!document.querySelector('.ck-sticky-panel')) {
                            ckeditor(autocomplete);
                        } else {
                            $('.ck-sticky-panel').show();
                        }
                    } else {
                        $('.ck-sticky-panel').hide();
                    }
                });
            } else if (communication_type == "email") {
                $('#message_type_field').hide();
                $('#global_header_field').show();
                $('#footer_field').show();
                $('#email_subject_field').show();
                $('#email_field').show();
                $('#communication_email').hide();
                $('.communication_type_hide').show();
                if (!document.querySelector('.ck-sticky-panel')) {
                    ckeditor(autocomplete);
                } else {
                    $('.ck-sticky-panel').show();
                }
            } else if (communication_type == "sms") {
                $('#message_type_field').hide();
                $('#global_header_field').hide();
                $('#email_subject_field').hide();
                $('#footer_field').hide();
                $('#email_field').hide();
                $('.ck-sticky-panel').hide();
            }
            if ((lowerCaseValue == 'global_header' || lowerCaseValue == 'global_footer') && communication_type == "email") {
                $('#global_header_field').hide();
                $('#footer_field').hide();
                $('#message_type_field').hide();
                $('#email_field').hide();
                $('#email_subject_field').hide();
                $('#communication_email').show();
                $('.communication_type_hide').hide();
                $('#communication_email').val('email');
            }
        }


        function editOption(alias) {
            let alias_data = alias;
            if (alias_data != 'add' && alias_data != 'global_header' && alias_data != 'global_footer') {
                $('#exampleModalLabel1').text('Option Edit');
                $("#option_edit").val(alias_data);
                $("#option_name").val(alias_data);
                let callAuto = document.getElementById('callAuto');
                callAuto.click();
            }
        }

        function deleteOption(alias) {
            let alias_data = alias;
            if (alias_data != 'add' && alias_data != 'global_header' && alias_data != 'global_footer') {
                if (confirm("Are you sure..?")) {
                    $.ajax({
                        "url": "{{ url('admin/delete-alias') }}",
                        "method": "post",
                        "data": {
                            "get_name": true,
                            "_token": "{{ csrf_token() }}",
                            "alias": alias_data
                        },
                        "success": function(result) {
                            location.reload();
                        }
                    });
                }
            }
        }

        // When the user clicks on the button, toggle between hiding and showing the dropdown content

        function myFunction() {
            document.getElementById("myDropdown").classList.add("show");
        }

        // Close the dropdown if the user clicks outside of it
        window.onclick = function(event) {
            if (!event.target.matches('#dropbtn')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                var i;
                for (i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        function removeTags() {
            var input = document.getElementById('content');
            content.value = content.value.replace(/(<([^>]+)>)/ig, "");
        }
    </script>
@endsection
