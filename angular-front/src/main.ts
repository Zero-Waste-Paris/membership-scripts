import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';

import { importProvidersFrom } from '@angular/core';
import { AppComponent } from './app/app.component';
import { LoginApiModule } from './app/generated/login/api.module';
import { Configuration as LoginConfiguration } from './app/generated/login/configuration';
import { Configuration } from './app/generated/api/configuration';
import { ApiModule } from './app/generated/api/api.module';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { withInterceptorsFromDi, provideHttpClient } from '@angular/common/http';
import { BrowserModule, bootstrapApplication } from '@angular/platform-browser';
import { provideAnimations } from '@angular/platform-browser/animations';

function clientConfigFactory(): Configuration {
	return new Configuration(buildClientsConfigParameters());
}
function loginClientConfigFactory(): LoginConfiguration {
	return new LoginConfiguration(buildClientsConfigParameters());
}
function buildClientsConfigParameters() {
	let host = window.location.host;
	let protocol = window.location.protocol;
	return {
		basePath: protocol + "//" + host
	}
}

bootstrapApplication(AppComponent, {
	providers: [
    importProvidersFrom(BrowserModule, FormsModule, ReactiveFormsModule, ApiModule.forRoot(clientConfigFactory), LoginApiModule.forRoot(loginClientConfigFactory)),
    provideHttpClient(withInterceptorsFromDi()),
    provideAnimations()
]
})
	.catch(err => console.error(err));
