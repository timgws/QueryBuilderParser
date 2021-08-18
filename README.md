# QueryBuilderParser

Status Label  | Status Value
--------------|-------------
Build | [![Build Status](https://travis-ci.org/timgws/QueryBuilderParser.svg?branch=master)](https://travis-ci.org/timgws/QueryBuilderParser)
Insights | [![SensioLabsInsight](https://insight.sensiolabs.com/projects/70403e01-ad39-4117-bdef-d0c09c382555/mini.png?branch=master)](https://insight.sensiolabs.com/projects/70403e01-ad39-4117-bdef-d0c09c382555)
Code Climate | [![Code Climate](https://codeclimate.com/github/timgws/QueryBuilderParser/badges/gpa.svg)](https://codeclimate.com/github/timgws/QueryBuilderParser)
Test Coverage | [![Coverage Status](https://coveralls.io/repos/github/timgws/QueryBuilderParser/badge.svg?branch=master)](https://coveralls.io/github/timgws/QueryBuilderParser?branch=master)

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
        array( 'name', 'email' )
    );

    $query = $qbp->parse($input['querybuilder'], $table);

    $rows = $query->get();
    return Response::JSON($rows);
```

![jQuery QueryBuilder](/querybuilder.png?raw=true "jQuery QueryBuilder")

This query when posted will create the following SQL query:

```sql
SELECT * FROM table_of_data_to_integrate WHERE `name` LIKE '%tim%' AND `email` LIKE '%@gmail.com'
```

## Getting results from MongoDB
```php
    use timgws\QueryBuilderParser;

    $table = DB::collection('data');
    $qbp = new QueryBuilderParser(
        // provide here a list of allowable rows from the query builder.
        // NOTE: if a row is listed here, you will be able to create limits on that row from QBP.
        array( 'name', 'email' )
    );

    $query = $qbp->parse($input['querybuilder'], $table);

    $rows = $query->get();
    return Response::JSON($rows);
```

This query when posted will create the following MongoDB query:

```json
    {
      "$and": [
        {
          "name": {
            "$regex": "tim"
          }
        },
        {
          "email": {
            "$regex": "@gmail\\.com$"
          }
        }
      ]
    }
```

Note that to use this you will need to install and configure `jenssegers/mongodb`.

# Integration examples

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

## Using JoinSupportingQueryBuilderParser

`JoinSupportingQueryBuilderParser` is a version of `QueryBuilderParser` that supports building even more complex queries.

```php
    $joinFields = array(
        'join1' => array(
            'from_table'      => 'master',
            'from_col'        => 'm_col',
            'to_table'        => 'subtable',
            'to_col'          => 's_col',
            'to_value_column' => 's_value',
        ),
        'join2' => array(
            'from_table'      => 'master2',
            'from_col'        => 'm2_col',
            'to_table'        => 'subtable2',
            'to_col'          => 's2_col',
            'to_value_column' => 's2_value',
            'not_exists'      => true,
        )
    );

    $table = DB::table('table_of_data_to_integrate');
    $jsqbp = new JoinSupportingQueryBuilderParser($fields, $this->getJoinFields());
    $test = $jsqbp->parse($json, $builder);
```

Which will build an SQL query similar to:

```sql
select * where exists (select 1 from `subtable` where subtable.s_col = master.m_col and `s_value` < ?)
```

For simple queries, `QueryBuilderParser` should be enough.

# Exporting CSV files

Just as a footnote, there are right ways to export CSV files, and there are wrong ways.

For the right way, check out the question on StackOverflow,
[How can I output a UTF-8 CSV in PHP that Excel will read properly?](http://stackoverflow.com/a/16766198/2143004)

## Reporting Issues

I use this code in a number of my projects, so if you do find an issue, please feel free to report it with [GitHub's bug tracker](https://github.com/timgws/QueryBuilderParser) for this project.

Alternatively, fork the project and make a pull request :)
