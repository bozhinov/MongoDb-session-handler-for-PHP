MongoDb session handler for PHP (5.6)
===================

Storing session in memory speeds code execution significantly, but most of all it helps you get rid of the files in the TEMP directory.
It also provides you, as any other database, with an easy access and control over the data stored.

The problem with the original code (https://github.com/cballou/MongoSession) was that it takes some time from the moment you send the data to the database to the moment it is actually available for query
If you click around quick enough (~less than 2 seconds apart on my setup) the session data will not yet be available to you.
It is the way MongoDb works, even if you set the write concerns to write directly to disk and disable any journaling.

What I did is use the experimental-not-for-production storage engine that came with version 3.0 ("inMemoryExperiment") or "ephemeralForTest" in version 3.2
It has been over an year now since I started using it and have not run into any bugs.
Of course, if you can afford it, use the official solution that comes with the enterprise version of MongoDb.

I will start working on the version for PHP 7x as soon as it is stable (both MongoDb driver and language)