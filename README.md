MongoDb mod for the RainTpl3 template engine (unofficial)
===================

I ran into a problem after using RainTpl engine for years - 
I had to stop tracking file change times and RainTpl3 relies on FILEMTIME to check if the template was updated.

My options were to switch to another engine or make this one work.
After googling for a while, I found out that RainTpl3 is perfect for the small projects I run.

So, I made it store templates in MongoDb database instead of a cache folder and used MD5_FILE to check if the template was updated.
Obviously, there is a performance issue here as MD5_FILE is way slower than FILEMTIME, so I added the 'production' option.
Once all of the templates are in cache, you can turn 'production' ON. (Test-Production-Ready.php)

I went a bit further and IMO made initialization and configuration simpler, making it blazing fast.

Here is the full change log:

- Simplified config (see examples for usage)
- Removed plugins
- Removed blacklist
- Removed option for extra tags
- Removed autoload, replaced with simple class include
- The parser code was somewhat reorganized
- Cache is stored in MongoDb GridFS
- Added 'production' option in case all templates are already in cache

Downsides
=============
- Using EVAL() for executing the code stored in the database
- Race conditions are possible, depending on MongoDb configuration (using of journal, storage engine, etc)
  Same problem makes it unsuitable for PHP session handler. Check my other project to see how I made that work.
- I only made it work and tested it for PHP 5.6 and MongoDb 3.0 & 3.2. I will start working on PHP7 as soon as it is stable enough
- Due to the use of MD5_FILE, you will only gain performance with 'production' option ON, 
  which makes it unsuitable for MongoDb with in-memory storage as cache will have to be rebuilt after every service restart

Code works great for me, I hope you enjoy it was well. Feedback is appreciated.
Please do not store the test scripts with your production code.

