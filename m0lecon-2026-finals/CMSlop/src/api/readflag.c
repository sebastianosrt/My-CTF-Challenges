#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>

int main() {
    setuid(0);
    FILE *f = fopen("/flag.txt", "r");
    if (!f) {
        perror("flag");
        return 1;
    }
    char buf[256];
    while (fgets(buf, sizeof(buf), f))
        printf("%s", buf);
    fclose(f);
    return 0;
}
