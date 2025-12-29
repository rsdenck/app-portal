CC=gcc
CFLAGS=-Wall -O2
LIBS=-lpcap

SRCS=dflow_main.c dflow_state.c dflow_l7.c
OBJS=$(SRCS:.c=.o)
TARGET=dflow-engine

all: $(TARGET)

$(TARGET): $(OBJS)
	$(CC) $(CFLAGS) -o $(TARGET) $(OBJS) $(LIBS)

%.o: %.c
	$(CC) $(CFLAGS) -c $< -o $@

clean:
	rm -f $(OBJS) $(TARGET)
