# Mongo Query Builder for PHP

Why? Because writing Mongo queries in PHP feels awkward and quickly becomes hard to read.

> This is still under development and the API might change until there's a 1.x-release.

_**Note:**_ This is just a wrapper for the `mongodb/mongodb`-library and you always have access to the underlying library if you want to do something this wrapper doesn't support.

## Usage

* [Install](#install)
* [Simple example](#simple-example)
* [Query builder](#query-builder)
    * [Insert](#insert)
    * [Get data](#get-data)
        * [Get list of items](#get-list-of-items)
        * [Get first item](#get-first-item)
        * [Find](#find)
        * [Count](#count)
        * [Where](#where)
            * [Operators](#operators)
        * [Or where](#or-where)
        * [Order by](#order-by)
        * [Limit](#limit)
        * [Skip](#skip)
        * [Select](#skip)
        * [Update](#update)
        * [Replace](#replace)
        * [Delete](#delete)
* [Additional](#additional)
    * [The MongoDB instances](#the-mongodb-instances)

## Install

Clone this repository or use composer (recommended) to download the library with the following command:
```cli
composer require maer/mongo-query
```

## Simple example

```php
// Load composers autoloader
include '/path/to/vendor/autoload.php';

// Instatiate with no arguments and a MongoDB\Client instance will be created
// (using localhost and default port)
$client = new Maer\MongoQuery\MongoQuery();

// To use a custom client intsance, pass it as the first argument
$client = $client = new Maer\MongoQuery\MongoQuery(
    new MongoDB\Client('mongodb://example.com:1234')
);


// Get a database
$db = $client->myDatabase; // Or: $client->getDatabase('myDatabase');

// Get a collection
$collection = $db->myCollection; // Or: $db->getCollection('myCollection');

// Add some criterias
$result = $collection->where('some-key', 'some-value')
    ->orderBy('some-key', 'asc')
    ->get();

// $result will now contain an array with the matched documents
```

This was just a simple example. Read more below to find out more...

## Query builder


### Insert

You can either insert one document

```php
$id = $collection->insert([
    'some-key' => 'some-value',
]);

// $id contains the new id for the inserted document or false on error

```

or you can insert many in one go

```php
$ids = $collection->insert([
    [
        'some-key' => 'some-value',
    ],
    [
        'some-key' => 'some-other-value',
    ]
]);

// $ids contains an array of the new ids for the inserted documents or false on error
```

### Get data

Most of the below items will return a multi dimensional array with the matching items.

#### Get list of items

```php
$result = $collection->get();
```

#### Get first item

Return the first matched item.

```php
$result = $collection->first();
```

#### Find

Get first item that matches a single condition.

```php
// Find by _id (default)
$result = $collection->find('123');

// Find by some other key (is equal to)
$result = $collection->find('some-value', 'some-key');
```

This is the only "getter" that can't have any other criteria.

#### Count

To get the total count of matched documents, use:

```php
$result = $collection->where('some-key', 'some-value')->count();
// $result is an integer with the number of matched documents
```

All methods that returns an actual result will also reset the query (remove any criteria previously added).

When it comes to `count()`, there might be situations where you don't want this (like when you build pagination). To keep all the criteria, you can pass `false` to the `count()`-method: `count(false)`.

If you pass `false`, you can do:


```php
$result = $colletion->get();
```

and it will only return the documents that matched the previous criteria.


#### Where

Usually, you only want to return some specific items which match some type of criteria.


```php
$result = $collection->where('some-key', 'some-value')->get();
```

The above will match all items that has `some-value` as the value for the key `some-key`. This equals: `where('some-key', '=', 'some-value')`.

##### Operators
There are many more operators you can use to narrow down the result.

The below operators are used like this: `where($column, $operator, $value)`

| Operator   | Description                |
|------------|----------------------------|
|   `=`      | Equal to                   |
|   `!=`     | Not equal to               |
|   `<`      | Lower than                 |
|   `>`      | Higher than                |
|   `<=`     | Lower or equal to          |
|   `>=`     | Higher or equal to         |
|   `*`      | Contains                   |
|   `=*`     | Starts with                |
|   `*=`     | Ends with                  |

You can add as many where conditions as you like to the same query. To make it easier, you can chain them:

```php
$result = $collection->where('some-key', '=', 'some-value')
    ->where('some-other-key', '!=', 'some-other-value')
    ...
    ->get();
```

#### Or Where

To add an or `$or`-block, use `orWhere()`:

```php
$collection->orWhere('some-key', 'some-value')
    ->orWhere('some-other-key', 'some-other-value');

// Same as:
// [
//     '$or' => [
//         [
//             'some-key' => 'some-value',
//         ],
//         [
//             'some-other-key' => 'some-other-value'
//         ]
//     ],
// ]
```

You can also group the or-credentials:

```php

$collection->orWhere(function ($query) {
    $query->where('some-key', 'some-value')
        ->where('some-other-key', 'some-other-value');
});


// Same as:
// [
//     '$or' => [
//         [
//             'some-key' => 'some-value',
//             'some-other-key' => 'some-other-value'
//         ]
//     ],
// ]
```

#### Order by

To sort the result in a specific way, you can use `orderBy($column, $order = 'asc')`.

```php
// Ascending order:
$result = $collection->orderBy('first_name');

// Descending order:
$result = $collection->orderBy('first_name', 'desc');
```

#### Limit

You can limit the amount of items returned.

```php
// Only get the 2 first matches
$result = $collection->people->limit(2)->get();
```

#### Skip

If you need to add an offset (for using with pagination, for example), you can define how many documents it should `skip()`:

```php
// Get all results from the second match and forward.
$result = $collection->offset(2)->get();
```

#### Select

You might only want to fetch some specific fields from the documents. You can define what fields to get using `select()`

```php
// Define what fields you want to get
$result = $collection->select(['some-key', 'some-other-key'])->get();
```

**Note:** This method will always return the `_id`-field as well


#### Pluck

If you only want to get one single field as an array with all the values, you can use `pluck()`

```php
$result = $collection->pluck('some-key');

// Returns
// [
//     'first-value',
//     'second-value',
//     ...
// ]
```

Any potential duplicates will also be removed.


### Update

To update an item, use `updateOne(array $data)`:

```php
$modified = $collection->where('some-key', 'some-value')
    ...
    ->updateOne([
        'some-key' => 'some-new-value',
    ]);
```

To update multiple items, use `updateMany(array $data)`:

```php
$modified = $collection->where('some-key', 'some-value')
    ...
    ->updateMany([
        'some-key' => 'some-new-value',
    ]);
```

These method returns the number of modified documents.

When updating, you only need to pass the fields and values you want to update. All other values will remain as is in the database.


### Replace

The difference between these methods and the update, is that these replaces the complete documents, except from the _id. Example:

```php
$modified = $collection->insert([
    'foo'     => 'Lorem',
    'bar'     => 'Ipsum',
]);

$modified = $collection->where('foo', 'Lorem')
    ->replaceOne([
        'foo_bar' => 'Lorem Ipsum',
    ]);

$result = $collection->first();
// Returns:
// [
//     '_id'      => xxx,
//     'foo_bar' => 'Lorem Ipsum',
// ]
```

To replace multiple documents, use `replaceMany($data)`

These method returns the number of modified documents.

### Delete

Delete items

```php
$result = $collection->where('some-key', 'some-value')
    ->deleteOne();
```

To delete multiple items, use `deleteMany()`.

These methods returns the number of modified documents.


## Additional

### The MongoDB instances

Sometimes you want to do some magic that this library doesn't support (yet). To do that, you might need to access the underlying MongoDB instances. You can do that with the `getInstance()`-methods.

```php
$mongo = new Maer\MongoQuery\MongoQuery;

// Get MongoDB\Client
$client = $mongo->getInstance();

// Get MongoDB\Database
$database = $mongo->someDatabase->getInstance();

// Get MongoDB\Collection
$collection = $mongo->someDatabase->someCollection->getInstance();

```

---
If you have any questions, suggestions or issues, let me know!

Happy coding!