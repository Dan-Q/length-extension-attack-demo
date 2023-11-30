# Length Extension Attack Demonstration

This repository contains a practical demonstration of a Length Extension Attack.

Companion/explanatory materials can be found in:

- [An in-depth blog post](https://danq.me/2023/11/30/length-extension-attack/)
- [A video based on the blog post](https://danq.me/2023/11/30/length-extension-attack-video/)
    - ...[also available on YouTube](https://www.youtube.com/watch?v=H_bvdhPMizE)
    - ...[also available on Facebook](https://www.facebook.com/DanQBlog/videos/634831868617045/)

## Prerequisites

- Docker (e.g. [Docker Desktop](https://www.docker.com/products/docker-desktop/))

## Running the demo

1. Clone this repository
2. Run `docker compose up` in the repository directory
3. Go to http://localhost:8818/ to see an imaginary stock image website
4. Run `docker exec hash_extender hash_extender` to execute [hash_extender](https://github.com/iagox86/hash_extender)

### Understanding the site

The site is a stock image website. It's implemented in a very basic way: there's no logic to control who has purchased access to each image: the code only aims to appropriately protect the two images that are made available.

The first image is called `'free'`, and the user has been granted access to it. The second image is called `'valuable'`, and the user is not allowed to download it. Access to download the images is controlled by using an SHA1 as a message authentication code, as follows:

1. The query string e.g. `download=free` is salted (prepended) with a secret key stored only on the server, and then run through SHA1
2. The URL given to the user includes this "download key", e.g. `/?download=free&key=ee1cce71179386ecd1f3784144c55bc5d763afcc`
3. When the link is followed, the `&key=...` part is removed and the same process followed to generate a hash; this hash is compared to the one provided by the user and only if they match is the download allowed

To see this, click the download link under the free image to see it. Try changing the word `free` in the URL to `valuable` and see that the download is rejected. Assume that it is not practical to correct the hash because you do not know the secret key that was used to salt it.

### Understanding hashing

Many hashing algorithms, including MD5, SHA1, SHA256, and SHA512, function by the following mechanism:

1. First, the content to be hashed (including any salt) is split into blocks of a fixed length.
2. The final block is padded to make it the correct length, as well as a footer to say how long the data part was.
3. The hashing function is executed on the first block. The function takes two inputs: (a) the contents of the block, and (b) a predefined initialisation vector (IV) - a constant provided by the hashing algorithm.
4. For each subsequent block, the input of the function is (a) the contents of the block, and (b) the _output from the function of the previous block_.
5. The output of the final block is the hash and is returned.

### Understanding hash extension

*I'm highly grateful to [Ron Bowes' excellent article](https://www.skullsecurity.org/2012/everything-you-need-to-know-about-hash-length-extension-attacks) in helping to explain this attack. For a deeper dive, you might like to take a look too.*

If we know the output of the previous block, we can calculate the hash of the next block without knowing the contents of the previous block(s).

We can manipulate the final block by adding our own padding, equivalent to that which would have been added to the block by the hashing algorithm. We can then add our own block, and the hashing algorithm will continue to process it from the point at which it left off.

We can derive the output that the hashing algorithm will produce, even without knowing what was contained in the previous blocks, by simply running the hashing algorithm over our _new_ block, but with the predefined IV manipulated to the output of the hashing algorithm for a known good hash.

### Understanding parameter addition

When a server-side application receives a request with _duplicated_ parameters (e.g. ?download=free&download=valuable), it will typically assume that the _final_ parameter is the one that should be used, and any previous instances of the same parameter should be discarded.

### Putting it all together

We know that:

1. A block containing (a) `download=free`, plus (b) an unknown salt, produces a known hash `ee1cce71179386ecd1f3784144c55bc5d763afcc`
2. The same hash would be produced by a block containing (a) `download=free`, plus (b) the unknown salt, plus (c) a number of padding bytes equal to the block length of the hashing algorithm minus the lenths of (a) and (b), so long as there was enough data to justify another block that follows it.
3. A block containing `&download=valuable`, where the IV is set to the output of hashing just the first block, would produce a valid hash.

So all we need to do is pad `download=free` with the appropriate padding to make it fill the block it's in (after accounting for the salt), then follow that with `&download=valuable`, and provide this awkward-looking query string (followed by our new hash). We'll end up with a URL that looks a bit like this:

```
http://localhost:8818/?download=free%80%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%e8&download=valuable&key=...
```

We can derive the length of the unknown salt by trial-and-error. In this example, though, I'll let you know that its length is 16. We can guess that the server is using SHA1 based on the length of the hash, or work it out by trial-and-error.

SHA1's blocks are 64 bytes long, and our secret key (length 16) plus `download=free` (length 13) fill 29 bytes. That's why we pad with 35 bytes of data: a `%80` to start the padding, plus a stack of `%00`s, then finally a `%E8` (hex E8 = decimal 232 bits, divided by 8 is 29 bytes: the length of the original data).

After the padding, we inject our own payload: `&download=valuable`. The hash we generate will be valid, but the `download` parameter will be overwritten from `free` to `valuable`.

### Generating the hash

We can use `hash_extender` to help us to generate the `key=` parameter for our manipulated URL, like this:

```
docker exec hash_extender hash_extender --format=sha1 --data="download=free" --secret=16 --signature=ee1cce71179386ecd1f3784144c55bc5d763afcc --append="&download=valuable" --out-data-format=html
```

The parameters we're passing are:

- The hash format (`sha1`) - if you don't provide this then `hash_extender` will try to detect it for you
- The known data (`download=free`) that was used to generate the hash (in reality, only the _length_ of this data is needed)
- The length of the unknown secret (`16`)
- The known valid signature (`ee1cce71179386ecd1f3784144c55bc5d763afcc`) from the valid URL
- The data we'd like to append (`&download=valuable`) after the padding
- The output format (`html`): `hash_extender` has several options but I find this one most-useful for attacking a web URL!

`hash_extender` will output a new signature (hash) for us as well as a string to replace `download=free` in the URL with (including all the padding we need). Note that it over-enthusiastically encodes HTML entities and this results in your `&` and `=` characters being encoded (to `%26` and `%3d` respectively), which isn't what you want: you'll need to manually change them back:

```
docker exec hash_extender hash_extender --format=sha1 --data="download=free" --secret=16 --signature=ee1cce71179386ecd1f3784144c55bc5d763afcc --append="&download=valuable" --out-data-format=html
Type: sha1
Secret length: 16
New signature: 7b315dfdbebc98ebe696a5f62430070a1651631b
New string: download%3dfree%80%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%e8%26download%3dvaluable
```

This ultimately results in a URL like this, which allows us to download the "valuable" image without paying for it:

```
http://localhost:8818/?download=free%80%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%00%e8&download=valuable&key=7b315dfdbebc98ebe696a5f62430070a1651631b
```
