# QueryBuilderParser

Status Label  | Status Value
--------------|-------------
Build | [![Build Status](https://travis-ci.org/timgws/QueryBuilderParser.svg?branch=master)](https://travis-ci.org/timgws/QueryBuilderParser)
Insights | [![SensioLabsInsight](https://insight.sensiolabs.com/projects/70403e01-ad39-4117-bdef-d0c09c382555/mini.png?branch=master)](https://insight.sensiolabs.com/projects/70403e01-ad39-4117-bdef-d0c09c382555)
Code Climate | [![Code Climate](https://codeclimate.com/github/timgws/QueryBuilderParser/badges/gpa.svg)](https://codeclimate.com/github/timgws/QueryBuilderParser)
Test Coverage | [![Test Coverage](https://codeclimate.com/github/timgws/QueryBuilderParser/badges/coverage.svg)](https://codeclimate.com/github/timgws/QueryBuilderParser/coverage)

**QueryBuilderParser** is designed mainly to be used inside Laravel projects, however it can be used outside Laravel
projects by using Illuminate/Database.

A simple to use query builder for the [jQuery QueryBuilder plugin](http://querybuilder.js.org/demo.html#plugins).

# Using QueryBuilderParser

## Building a new query from QueryBuilder rules.

```php
    use timgws\QueryBuilderParser;

    $table = DB::table('table_of_data_to_integrate');
    $qbp = new QueryBuilderParser(
        // provide here a list of allowable rows from the query builder.
        // NOTE: if a row is listed here, you will be able to create limits on that row from QBP.
        array( 'row1', 'row2', 'row3' )
    );

    $query = $qbp->parse($input['querybuilder'], $table);

    $rows = $query->get();
    return Response::JSON($rows);
```

## Integrating with jQuery Datatables

Mixed with Datatables, jQuery QueryBuilder makes for some true awesome, allowing limitless options
for filtering data, and seeing the results on the fly.

```php
    use timgws\QueryBuilderParser;
    
    class AdminUserController {
        function displayUserDatatable() {
            /* builder is POST'd by the datatable */
            $queryBuilderJSON = Input::get('rules');
            
            $show_columns = array('id', 'username', 'email_address');
            
            $query = new QueryBuilderParser($show_columns);
            
            /** Illuminate/Database/Query/Builder $queryBuilder **/
            $queryBuilder = $query->parse(DB::table('users'));
            
            return Datatable::query($queryBuilder)
                ->showColumns($show_columns)
                ->orderColumns($show_columns)
                ->searchColumns($show_columns)
                ->make()
        }
    }
```

On the client side, a little bit of magic is required to make everything work.

```js
    // the default rules, what will be used on page loads...
    var datatablesRequest = {};
    var _rules = defaultRules = {"condition":"AND","rules":[
        {"id":"active","field":"active","type":"integer","input":"radio","operator":"equal","value":"1"}
    ]};

    // a button/link that is used to update the rules.
    function updateFilters() {
        _rules = $('#querybuilder').queryBuilder('getRules');
        reloadDatatables();
    }

    function filterChange() {
        var _json = JSON.stringify( _rules );
        datatablesRequest = { rules: _json };
    }

    filterChange();

    function reloadDatatables() {
        /* Datatables first... */
        filterChange();

        $('.dataTable').each(function() {
            dt = $(this).dataTable();
            dt.fnDraw();
        })
    }

    jQuery(document).ready(function(){
        // dynamic table
        oTable = jQuery('.datatable').dataTable({
            "fnServerParams": function(aoData) {
                // add the extra parameters from the jQuery QueryBuilder to the Datatable endpoint...
                $.each(datatablesRequest , function(k,v){
                    aoData.push({"name": k, "value": v});
                })
            }
        })
    });
```

# Exporting CSV files

Just as a footnote, there are right ways to export CSV files, and there are wrong ways.

For the right way, check out the question on StackOverflow,
[How can I output a UTF-8 CSV in PHP that Excel will read properly?](http://stackoverflow.com/a/16766198/2143004)

## Reporting Issues

I use this code in a number of my projects, so if you do find an issue, please feel free to report it with [GitHub's bug tracker](https://github.com/timgws/QueryBuilderParser) for this project.

Alternatively, fork the project and make a pull request :)
