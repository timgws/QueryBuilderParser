# QueryBuilderParser

Status Label  | Status Value
--------------|-------------
Build | [![Build Status](https://travis-ci.org/timgws/QueryBuilderParser.svg?branch=master)](https://travis-ci.org/timgws/QueryBuilderParser)

**QueryBuilderParser** is designed mainly to be used inside Laravel projects, however it can be used outside Laravel
projects by using Illuminate/Database.

A simple to use query builder for the [jQuery QueryBuilder plugin](http://mistic100.github.io/jQuery-QueryBuilder/).

    use timgws\QueryBuilderParser;

    $table = DB::table('table_of_data_to_interegate');
    $qbp = new QueryBuilderParser(
        array( 'row1', 'row2', 'row3' )
    );

    $query = $qbp->parse($input['querybuilder'], $table);

    $rows = $query->get();
    return Response::JSON($rows);

Mixed with Datatables, this makes for some true awesome.
