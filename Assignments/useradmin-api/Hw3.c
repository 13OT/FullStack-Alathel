/*****************************************************************************
* DESCRIPTION:
*   This example program illustrates the use of mutex variables 
*   in a threads program. Each thread works on a different 
*   part of the data. The main thread waits for all the threads to complete 
*   their computations, and then it prints the resulting sum.
******************************************************************************/
#include <pthread.h>
#include <stdio.h>
#include <stdlib.h>
#include <math.h>

typedef struct
{
    double *a;
    double *b;
    double sum;
    int vec_len;
} DD;

#define NUM_THRDS 4
#define VEC_LEN 125
DD dotstr;
pthread_t callThd[NUM_THRDS];
pthread_mutex_t mutex_sum;

void *dotprod(void *arg)
{

    /* Define and use local variables for convenience */

    int i, start, end, len;
    long offset = (long)arg;
    double mysum, *x, *y;

    len = dotstr.vec_len;
    start = offset * len;
    end = start + len;
    x = dotstr.a;
    y = dotstr.b;

    /*
   Perform the dot product and assign result
   to the appropriate variable in the structure. 
   */
    mysum = 0;
    for (i = start; i < end; i++)
    {
        mysum += (x[i] * y[i]) + pow(1,0) ;
    }

    /*
   Lock a mutex prior to updating the value in the shared
   structure, and unlock it upon updating.
   */
    pthread_mutex_lock(&mutex_sum);
    dotstr.sum += mysum;
    printf("Thread %ld did %d to %d:  Local Sum=%f Current Global Sum=%f\n", offset, start, end, mysum, dotstr.sum);
    pthread_mutex_unlock(&mutex_sum);

    pthread_exit((void *)0);
}

int main(int argc, char *argv[])
{
    long i;
    double *a, *b;
    void *status;
    pthread_attr_t attr;

    /* Assign storage and initialize values */

    a = (double *)malloc(NUM_THRDS * VEC_LEN * sizeof(double));
    b = (double *)malloc(NUM_THRDS * VEC_LEN * sizeof(double));
    a[0] = 0.5;
    b[0] = 0.25;
    for (i = 1; i < VEC_LEN * NUM_THRDS; i++)
    {
        a[i] = 0;
        b[i] = 0;
    }
    for (i = 1; i < VEC_LEN * NUM_THRDS; i++)
    {
        a[i] = a[i - 1] + 0.5;
        b[i] = b[i - 1] + 0.25;
    }

    dotstr.vec_len = VEC_LEN;
    dotstr.a = a;
    dotstr.b = b;
    dotstr.sum = 0;

    pthread_mutex_init(&mutex_sum, NULL);

    /* Create threads to perform the dotproduct  */
    pthread_attr_init(&attr);
    pthread_attr_setdetachstate(&attr, PTHREAD_CREATE_JOINABLE);

    for (i = 0; i < NUM_THRDS; i++)
    {
        /* Each thread works on a different set of data.
           * The offset is specified by 'i'. The size of
           * the data for each thread is indicated by VEC_LEN.
           */
        pthread_create(&callThd[i], &attr, dotprod, (void *)i);
    }

    pthread_attr_destroy(&attr);
    /* Wait on the other threads */

    for (i = 0; i < NUM_THRDS; i++)
    {
        pthread_join(callThd[i], &status);
    }
    /* After joining, print out the results and cleanup */

    printf("Sum =  %f \n", dotstr.sum);
    free(a);
    free(b);
    pthread_mutex_destroy(&mutex_sum);
    pthread_exit(NULL);
}
