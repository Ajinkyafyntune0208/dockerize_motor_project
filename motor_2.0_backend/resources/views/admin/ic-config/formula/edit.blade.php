@extends('admin_lte.layout.app', ['activePage' => 'edit-expression', 'titlePage' => __('Update Formula')])

@section('content')
<link rel="stylesheet" href="{{asset('admin1/css/ic-config/style.css')}}">
<div class="col-lg-12 grid-margin stretch-card">

    @php
    function prepareElements($item, $selectedLabels, $selectedFormula, $operators, $selectedPlaceHolders, $selectedBuckets)
    {
        $skip = false;
        if ($item['type'] == 'if') {
            echo '<div class="list-group-item formula-item conditional-item moved-item selected" data-type="conditional" data-selected="true" data-value="if-else" data-id="if-else" draggable="false" style="">
                <div class="condition-part">condition';
            foreach ($item['value'] as $key => $ifValue) {
                if ($skip) {
                    continue;
                    $skip = false;
                }
                if (!is_array($ifValue['value'])) {
                    if ($ifValue['value'] == '?') {
                        echo '</div><div class="true-condition-part px-5">true statement';
                            continue;
                        
                    } elseif ($ifValue['value'] == ':') {
                        $isFalsestarted = true;
                        echo '</div><div class="false-condition-part px-5">false statement';
                            $nextKey = $key + 1;

                            if (isset($item['value'][$nextKey])) {
                                prepareElements($item['value'][$nextKey], $selectedLabels, $selectedFormula, $operators, $selectedPlaceHolders, $selectedBuckets);
                                $skip = true;
                            }
                            echo '</div>';
                            continue;
                    } else {
                        prepareElements($ifValue, $selectedLabels, $selectedFormula, $operators, $selectedPlaceHolders, $selectedBuckets);
                    }
                } else {
                    prepareElements($ifValue, $selectedLabels, $selectedFormula, $operators, $selectedPlaceHolders, $selectedBuckets);
                }
            }

            echo '</div>';

        } elseif (in_array($item['type'], ['round', 'ceil', 'floor'])) {
            $type = $item['type'];
            echo '<div class="funtion-part moved-item selected" data-type="function" data-selected="true" data-value="'.$type.'" data-id="'.$type.'" draggable="false">
                                        <div>
                                            '.ucfirst($type).'
                                        </div> 
                                        <div class="round-list functional-list">';
            foreach ($item['value'] as $key => $content) {
                prepareElements($content, $selectedLabels, $selectedFormula, $operators, $selectedPlaceHolders, $selectedBuckets);
            }

            echo '</div></div>';
        }
        $element  = '';
        if ($item['type'] == 'formula') {
            $listItem = $selectedFormula->where('id', $item['value'])->first();
            $element = '<div class="list-group-item formula-item moved-item selected" data-type="expression" data-selected="true" data-value="'.$listItem?->formula_name.'" data-id="'.$listItem?->id.'" draggable="false" title="Expression">'.$listItem?->formula_name.'</div>';
        } elseif ($item['type'] == 'label') {
            $listItem = $selectedLabels->where('id', $item['value'])->first();
            $title = "Label of '".$listItem->label_group."'";
            $element = '<div class="list-group-item formula-item moved-item selected" data-type="premium" data-selected="true" data-value="'.$listItem?->label_key.'" data-id="'.$listItem?->id.'" draggable="false" title="'.$title.'">'.$listItem?->label_name.'</div>';
        } elseif ($item['type'] == 'bucket') {
            $listItem = $selectedBuckets->where('id', $item['value'])->first();
            $element = '<div class="list-group-item formula-item moved-item selected" data-type="bucket" data-selected="true" data-value="'.$listItem?->bucket_name.'" data-id="'.$listItem?->id.'" draggable="false" title="Bucket">'.$listItem?->bucket_name.'</div>';
        } elseif ($item['type'] == 'place-holder') {
            $listItem = $selectedPlaceHolders->where('id', $item['value'])->first();
            $element = '<div class="list-group-item formula-item moved-item selected" data-type="place-holder" data-selected="true" data-value="'.$listItem?->placeholder_name.'" data-id="'.$listItem?->id.'" draggable="false" title="Place Holder">'.$listItem?->placeholder_name.'</div>';
        } elseif ($item['type'] == 'plain_text') {
            $element = '<div class="list-group-item formula-item p-0 m-0 moved-item selected plain-text"data-type="plain-text" data-selected="true" data-value="" data-id="" draggable="false" style=""><input type="number" class="" placeholder="Plain Text" style="margin: 0; padding:0" onkeydown="validatePlainText(event)" oninput="createFormula()" value="'.$item['value'].'">
                        </div>';
        } elseif ($item['type'] == 'op') {
            $op = array_search($item['value'], $operators);
            $value = $item['value'];
            $element = '<div class="list-group-item formula-item moved-item selected" data-type="operator" data-selected="true" data-value="'.$value.'" data-id="'.$value.'" draggable="false" style="">'.$op.'</div>';
        }
        
        if (!empty($element)) {
            echo $element;
        }
    }
@endphp
    <div class="card">
        <div class="card-body">
            <form action="" method="post" class="submit-form">
                @csrf
                <div class="row d-none formula-form">

                </div>
                <div class="row">
                    <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                        <label for="" class="required">Formula Name</label>
                        <input type="text" class="form-control" name="expressionName" value="{{$formula->formula_name}}" pattern="[A-Za-z0-9_]+" title="Only alphanumeric characters and underscores are allowed." required>
                        @error('expressionName')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-6 col-lg-4 col-xl-3 form-group formula-group">
                        <label for="">Formula Matrix</label>
                        <input type="text" class="form-control" name="formula" value="{{$formula->matrix}}" required readonly>
                        @error('formula')<span class="text-danger formula-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="" style="visibility: hidden">button groups</label>

                        <div class="row">
                            <button class="btn btn-success mr-1" type="submit">Save</button>
                            <a href="{{route('admin.ic-configuration.formula.list-formula')}}" class="btn btn-danger">Cancel</a>
                        </div>
                        
                    </div>
                </div>
                <hr>
            </form>
            <div class="row mt-1">
                <div class="col-3" style="max-height: 60vh; overflow-y:scroll">
                    <div class="accordion" id="expressionsAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button bg-secondary" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#expressionsAccordionCollapse"
                                    aria-expanded="true" aria-controls="expressionsAccordionCollapse">
                                    Expressions
                                </button>
                            </h2>


                            <div id="expressionsAccordionCollapse" class="accordion-collapse collapse"
                                aria-labelledby="headingOne" data-bs-parent="#expressionsAccordionCollapse">
                                <div class="accordion-body">
                                    <div id="expressions-list" class="list-group">
                                        @foreach ($formulas as $f)
                                        <div class="list-group-item formula-item" data-type="expression"
                                            data-selected="false" data-value="{{$f->formula_name}}"
                                            data-id="{{$f->id}}" title="Expression">
                                            {{$f->formula_name}}
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button bg-secondary" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#bucketsAccordionCollapse"
                                    aria-expanded="true" aria-controls="bucketsAccordionCollapse">
                                    Bucket Groups
                                </button>
                            </h2>


                            <div id="bucketsAccordionCollapse" class="accordion-collapse collapse"
                                aria-labelledby="headingOne" data-bs-parent="#bucketsAccordionCollapse">
                                <div class="accordion-body">
                                    <div id="bucket-list" class="list-group">
                                        @foreach ($buckets as $f)
                                        <div class="list-group-item formula-item" data-type="bucket"
                                            data-selected="false" data-value="{{$f->bucket_name}}"
                                            data-id="{{$f->id}}" title="Bucket">
                                            {{$f->bucket_name}}
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button bg-secondary" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#placeHoldersAccordionCollapse"
                                    aria-expanded="true" aria-controls="placeHoldersAccordionCollapse">
                                    Place Holders
                                </button>
                            </h2>


                            <div id="placeHoldersAccordionCollapse" class="accordion-collapse collapse"
                                aria-labelledby="headingOne" data-bs-parent="#placeHoldersAccordionCollapse">
                                <div class="accordion-body">
                                    <div id="place-holder-list" class="list-group">
                                        @foreach ($placeHolders as $f)
                                        <div class="list-group-item formula-item" data-type="place-holder"
                                            data-selected="false" data-value="{{$f->placeholder_name}}"
                                            data-id="{{$f->id}}" title="Place Holder">
                                            {{$f->placeholder_name}}
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    
                    <hr/>
                    
                    @foreach ($labels as $group => $labels)
                    <div class="accordion" id="{{Str::snake($group)}}">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button bg-secondary" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#{{Str::snake($group.'collapse')}}"
                                    aria-expanded="true" aria-controls="{{Str::snake($group.'collapse')}}">
                                    {{$group}}
                                </button>
                            </h2>


                            <div id="{{Str::snake($group.'collapse')}}" class="accordion-collapse collapse"
                                aria-labelledby="headingOne" data-bs-parent="#{{Str::snake($group.'collapse')}}">
                                <div class="accordion-body">
                                    <div id="{{Str::snake($group). '-premium-list'}}" class="list-group">
                                        @foreach ($labels as $l)
                                        <div class="list-group-item formula-item" data-type="premium"
                                            data-selected="false" data-value="{{$l['label_key']}}"
                                            data-id="{{$l['id']}}" title="Label of '{{$group}}'">
                                            {{$l['label_name']}}
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    @endforeach

                    <div class="other-list">
                        <div class="list-group-item formula-item mt-2" data-type="plain-text" data-selected="false" data-value=""
                            data-id="">
                            <input type="number" class="" placeholder="Plain Text" style="margin: 0; padding:0" onkeydown="validatePlainText(event)" oninput="createFormula()">
                        </div>
                    </div>
                </div>

                <div class="col-9">
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-center formula-title">Formula</h6>
                            <div id="select-list">
                                @foreach ($extractedFormula as $item)
                                @php
                                    prepareElements($item, $selectedLabels, $selectedFormula, $operators, $selectedPlaceHolders, $selectedBuckets);
                                @endphp
                                @endforeach
                            </div>
                        </div>

                        <div class="col-12 text-center">
                            <div class="operator-list">
                                @foreach ($operators as $key => $item)
                                <div class="list-group-item formula-item" data-type="operator" data-selected="false"
                                    data-value="{{$item}}" data-id="{{$item}}">
                                    {{$key}}
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-12 text-center mt-2">
                            <h6 class="text-center">Conditional Operator</h6>
                            <div class="conditional-operator-list">
                                <div class="list-group-item formula-item conditional-item" data-type="conditional" data-selected="false"
                                    data-value="if-else" data-id="if-else">
                                    <div class="condition-part">
                                        conditon
                                    </div>
                                    <div class="true-condition-part px-5">
                                        true statement
                                    </div>
                                    <div class="false-condition-part px-5">
                                        false statement
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="col-12 text-center mt-2">
                            <h6 class="text-center">Others</h6>
                            <div class="function-list">
                                <div class="list-group-item formula-item function-item">
                                    <div class="funtion-part" data-type="function" data-selected="false"
                                    data-value="round" data-id="round">
                                        <div>
                                            Round
                                        </div> 
                                        <div class="round-list functional-list">

                                        </div>
                                    </div>
                        
                                </div>
                                <div class="list-group-item formula-item function-item">
                                    <div class="funtion-part"  data-type="function" data-selected="false"
                                    data-value="ceil" data-id="ceil">
                                        <div>
                                            Ceil
                                        </div> 
                                        <div class="ceil-list functional-list">

                                        </div>
                                    </div>
                        
                                </div>
                                <div class="list-group-item formula-item function-item">
                                    <div class="funtion-part" data-type="function" data-selected="false"
                                    data-value="floor" data-id="floor">
                                        <div>
                                            Floor
                                        </div> 
                                        <div class="floor-list functional-list">

                                        </div>
                                    </div>
                        
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<script>
    const labelGroups = @json($labelGroups);
    const action = 'UPDATE';
    const formulaId = '{{$formula->id}}'
    const formulaExistsUrl = "{{route('admin.ic-configuration.formula.check-formula')}}";
</script>
@endsection
@section('scripts')
<script src="{{asset('admin1/js/Sortable.js')}}"></script>
<script src="{{asset('admin1/js/ic-config/formula.js')}}"></script>
@endsection