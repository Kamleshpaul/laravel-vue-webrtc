<template>
	<app-layout>
		<template #header>
			<h2 class="font-semibold text-xl text-gray-800 leading-tight">
				Laravel vue WebRTC
			</h2>
		</template>

		<div class="py-12">
			<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
				<div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
					<div class="p-5">
						<h1>Users</h1>
						<ul class="flex p-2">
							<li
								v-for="user in users"
								:key="user.id"
								class="bg-gray-500 h-16 w-16 m-4 rounded-full text-white relative cursor-pointer"
								@click="startCall(user.id)"
							>
								<span
									class="absolute top-3 left-6 font-bold text-3xl"
									>{{ user.name.charAt(0) }}</span
								>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<Modal :show="ModelShow">
			<div class="p-5">
				<h2>{{ caller ? caller.name : "" }} is Calling...</h2>

				<div class="flex">
					<button
						class="bg-green-300 hover:bg-green-400 text-green-800 font-bold py-2 px-4 rounded inline-flex items-center text-white m-3"
					>
						Answer
					</button>
					<button
						@click="rejectCall"
						class="bg-red-300 hover:bg-red-400 text-red-800 font-bold py-2 px-4 rounded inline-flex items-center text-white m-3"
					>
						Reject
					</button>
				</div>
			</div>
		</Modal>
	</app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import Modal from "@/Jetstream/Modal";

export default {
	props: ["users", "user"],

	data() {
		return {
			caller: null,
			ModelShow: false,
		};
	},
	components: {
		AppLayout,
		Modal,
	},

	methods: {
		startCall(id) {
			if (id == this.user.id) {
				alert("can't call to own");
				return false;
			}
			axios
				.post(route("start.call"), { id, _token: csrfToken })
				.then(({ data }) => {
					console.log(data);
				});
		},

		rejectCall() {
			this.ModelShow = false;
		},
	},

	mounted() {
		Echo.channel(`call.${this.user.id}`).listen("StartCall", (e) => {
			this.caller = e.caller;
			this.ModelShow = true;
		});
	},
};
</script>
