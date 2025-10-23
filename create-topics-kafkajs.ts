import { Kafka } from 'kafkajs';

const kafka = new Kafka({
  clientId: 'topic-creator',
  brokers: ['10.70.1.23:9092'],
});

const admin = kafka.admin();

const topics = [
  'command-center-inbox',
  'service-1-topic',
  'service-1a-topic',
  'service-1b-topic',
  'service-1c-topic',
  'service-2-topic',
];

async function createTopics() {
  console.log('==========================================');
  console.log('Creating Kafka Topics');
  console.log('==========================================\n');

  try {
    await admin.connect();
    console.log('✓ Connected to Kafka broker at 10.70.1.23:9092\n');

    for (const topicName of topics) {
      console.log(`Creating topic: ${topicName}`);
      try {
        await admin.createTopics({
          topics: [
            {
              topic: topicName,
              numPartitions: 3,
              replicationFactor: 1,
              configEntries: [
                {
                  name: 'retention.ms',
                  value: '604800000', // 7 days
                },
              ],
            },
          ],
        });
        console.log(`✓ Topic '${topicName}' created successfully\n`);
      } catch (error: any) {
        if (error.message.includes('already exists')) {
          console.log(`⚠ Topic '${topicName}' already exists\n`);
        } else {
          console.log(`✗ Failed to create topic '${topicName}': ${error.message}\n`);
        }
      }
    }

    // List all topics
    console.log('==========================================');
    console.log('Current Topics:');
    console.log('==========================================');
    const existingTopics = await admin.listTopics();
    existingTopics.forEach((topic) => console.log(`- ${topic}`));
    console.log('\n✓ All topics ready!');
  } catch (error) {
    console.error('Error:', error);
    process.exit(1);
  } finally {
    await admin.disconnect();
  }
}

createTopics();
