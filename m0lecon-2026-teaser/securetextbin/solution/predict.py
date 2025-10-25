#!/usr/bin/python3
"""
Modified version of https://raw.githubusercontent.com/PwnFunction/v8-randomness-predictor/refs/heads/main/main.py
"""
import z3
import struct
import sys
import math
import json

B = 10**12
TWO52 = 1 << 52
MASK64 = (1 << 64) - 1

"""
Solving for seed states in XorShift128+ used in V8
> https://v8.dev/blog/math-random
> https://apechkurov.medium.com/v8-deep-dives-random-thoughts-on-math-random-fb155075e9e5
> https://blog.securityevaluators.com/hacking-the-javascript-lottery-80cc437e3b7f
"""

sequence = json.loads(sys.argv[1])

"""
Random numbers generated from xorshift128+ is used to fill an internal entropy pool of size 64
> https://github.com/v8/v8/blob/7a4a6cc6a85650ee91344d0dbd2c53a8fa8dce04/src/numbers/math-random.cc#L35

Numbers are popped out in LIFO(Last-In First-Out) manner, hence the numbers presented from the entropy pool are reveresed.
"""
# sequence = sequence[::-1] # already exploit.js passes the sequence already reversed

solver = z3.Solver()

"""
Create 64 bit states, BitVec (uint64_t)
> static inline void XorShift128(uint64_t* state0, uint64_t* state1);
> https://github.com/v8/v8/blob/a9f802859bc31e57037b7c293ce8008542ca03d8/src/base/utils/random-number-generator.h#L119
"""
se_state0, se_state1 = z3.BitVecs("se_state0 se_state1", 64)

for i in range(len(sequence)):
    y = int(sequence[i])

    """
    > https://github.com/v8/v8/blob/a9f802859bc31e57037b7c293ce8008542ca03d8/src/base/utils/random-number-generator.h#L119
    // Static and exposed for external use.
    static inline void XorShift128(uint64_t* state0, uint64_t* state1) {
      uint64_t s1 = *state0;
      uint64_t s0 = *state1;
      *state0 = s0;
      s1 ^= s1 << 23;
      s1 ^= s1 >> 17;
      s1 ^= s0;
      s1 ^= s0 >> 26;
      *state1 = s1;
    }
    """
    se_s1 = se_state0
    se_s0 = se_state1
    se_state0 = se_s0
    se_s1 ^= se_s1 << 23
    se_s1 ^= z3.LShR(se_s1, 17)
    se_s1 ^= se_s0
    se_s1 ^= z3.LShR(se_s0, 26)
    se_state1 = se_s1

    # m = Math.random()
    # y = floor(m*1e12) => m in [y/1e12, (y+1)/1e12)
    lo = math.ceil((TWO52) * y / B)
    hi = math.floor(((TWO52) * (y + 1) - 1) / B)
    
    m_z3 = z3.LShR(se_state0, 12)

    solver.add(z3.UGE(m_z3, z3.BitVecVal(lo, 64)))
    solver.add(z3.ULE(m_z3, z3.BitVecVal(hi, 64)))

"""
Since we recover the states from the last observation to the first, to get the next double we have to
revert the states: https://littlemaninmyhead.wordpress.com/2025/08/31/inverting-the-xorshift128-random-number-generator/
"""
def undo_xor_right(v, shift):
    x = 0
    while v:
        x ^= v
        v >>= shift
    return x
def undo_xor_left(v, shift):
    x = 0
    while v:
        x ^= v
        v = (v << shift) & MASK64
    return x

def xorshift128_step_backward(state0_new, state1_new):
    b = state0_new
    a2 = (state1_new ^ b ^ (b >> 26))
    a1 = undo_xor_right(a2, 17)
    s1_old = undo_xor_left(a1, 23)
    state0_old = s1_old
    state1_old = b
    return state0_old, state1_old

"""
static inline double ToDouble(uint64_t state0) {
  // Exponent for double values for [1.0 .. 2.0)
  static const uint64_t kExponentBits = uint64_t{0x3FF0000000000000};
  uint64_t random = (state0 >> 12) | kExponentBits;
  return base::bit_cast<double>(random) - 1;
}
"""
def to_double(state0):
    u64 = ((state0 >> 12) | 0x3FF0000000000000) & MASK64
    return struct.unpack("d", struct.pack("<Q", u64))[0] - 1.0

"""
https://github.com/v8/v8/blob/a9f802859bc31e57037b7c293ce8008542ca03d8/src/base/utils/random-number-generator.cc#L114C1-L114C45
double RandomNumberGenerator::NextDouble() {
  XorShift128(&state0_, &state1_);
  return ToDouble(state0_);
}
"""
def next_double(state0, state1):
    state0, state1 = xorshift128_step_backward(state0, state1);
    return state0, state1, to_double(state0);


if solver.check() == z3.sat:
    model = solver.model()

    states = {}
    for state in model.decls():
        states[state.__str__()] = model[state]

    state0 = states["se_state0"].as_long()
    state1 = states["se_state1"].as_long()

    curr = math.floor(to_double(state0)*10**12)
    # print(curr)

    state0, state1, next1 = next_double(state0, state1)
    nextn = math.floor(next1*10**12)

    print(nextn)