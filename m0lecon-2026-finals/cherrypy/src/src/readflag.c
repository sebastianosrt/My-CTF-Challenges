#include <stdio.h>

int main(void) {
    FILE *fp = fopen("/flag.txt", "r");
    if (!fp) {
        perror("fopen");
        return 1;
    }

    char buf[256];
    while (fgets(buf, sizeof(buf), fp)) {
        printf("%s", buf);
    }

    fclose(fp);
    return 0;
}
