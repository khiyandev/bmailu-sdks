'use strict';
// n8n credential: a Bmailu API key (bmk_...) and the API base URL.
class BmailuApi {
  constructor() {
    this.name = 'bmailuApi';
    this.displayName = 'Bmailu API';
    this.documentationUrl = 'https://bmailu.com/docs/';
    this.icon = 'file:bmailu.svg';
    this.properties = [
      {
        displayName: 'API Key',
        name: 'apiKey',
        type: 'string',
        typeOptions: { password: true },
        default: '',
        required: true,
        description: 'A full-access or sending-only key (bmk_…) from the Developers page of the Bmailu panel',
      },
      {
        displayName: 'Base URL',
        name: 'baseUrl',
        type: 'string',
        default: 'https://app.bmailu.com/api/v1',
        description: 'Change only for testing',
      },
    ];
    this.authenticate = {
      type: 'generic',
      properties: {
        headers: { Authorization: '=Bearer {{$credentials.apiKey}}' },
      },
    };
    this.test = {
      request: {
        baseURL: '={{$credentials.baseUrl}}',
        url: '/emails',
        qs: { limit: 1 },
      },
    };
  }
}

module.exports = { BmailuApi };
