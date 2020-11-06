<template>
	<app-layout>
		<template #header>
			<h2 class="font-semibold text-xl text-gray-800 leading-tight">
				Laravel Vue WebRTC
			</h2>
		</template>

		<div class="py-12">
			<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
				<div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
					<div class="p-5">
						<h1>Users</h1>
						<ul class="flex p-2">
							<li
								v-for="row in users"
								v-show="row.id != user.id"
								:key="row.id"
								class="bg-gray-500 h-16 w-16 m-4 rounded-full text-white relative cursor-pointer"
								@click="startCall(row.id)"
							>
								<span
									class="absolute top-3 left-6 font-bold text-3xl adjust-margin"
									>{{ row.name.charAt(0) }}</span
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
						@click="AnswerCall(caller)"
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

		<Modal :show="isCallOn">
			<div class="p-5">
				<h2>Video Room</h2>
				<div class="flex">
					<video id="myVideo" class="w-1/2"></video>
					<video id="otherVideo" class="w-1/2"></video>
				</div>

				<button
					@click="EndCall"
					class="bg-red-300 hover:bg-red-400 text-red-800 font-bold py-2 px-4 rounded inline-flex items-center text-white m-3"
				>
					Close
				</button>
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
			isCallOn: false,
			offerData: null,
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
			this.isCallOn = true;
			this.ModelShow = false;
			const peerConnection = new RTCPeerConnection();
			navigator.mediaDevices
				.getUserMedia({ video: true, audio: true })
				.then((stream) => {
					let video = document.getElementById("myVideo");
					if ("srcObject" in video) {
						video.srcObject = stream;
					} else {
						video.src = window.URL.createObjectURL(stream);
					}
					peerConnection.addStream(stream);
					peerConnection
						.createOffer()
						.then((sdp) => {
							peerConnection.setLocalDescription(sdp);
						})
						.then(() => {
							axios.post(route("start.call"), {
								id,
								_token: csrfToken,
								data: peerConnection.localDescription,
							});
						});
					video.play();
				})
				.catch((err) => {
					console.log(err);
					alert("Access denied");
					this.isCallOn = false;
				});

			Echo.channel(`call.${id}`).listen("AnswerCall", (e) => {
				peerConnection.setRemoteDescription(e.data);
			});
		},

		rejectCall() {
			this.ModelShow = false;
		},

		AnswerCall(user) {
			this.isCallOn = true;
			this.ModelShow = false;

			if (!this.offerData) {
				alert("Bad Offer");
			}
			console.log(this.offerData);
			const peerConnection2 = new RTCPeerConnection();
			peerConnection2
				.setRemoteDescription(this.offerData)
				.then(() => {
					peerConnection2.createAnswer();
				})
				.then((sdp) => {
					peerConnection2.setLocalDescription(spd);
				})
				.then((data) => {
					axios.post(route("answer.call"), {
						id: user.id,
						_token: csrfToken,
						data: peerConnection2.localDescription,
					});
				});

			peerConnection2.onaddstream = (e) => {
				let video = document.getElementById("otherVideo");
				if ("srcObject" in video) {
					video.srcObject = e.stream;
				} else {
					video.src = window.URL.createObjectURL(e.stream);
				}
				video.play();
			};
		},
		EndCall() {
			this.isCallOn = false;
		},
	},

	mounted() {
		Echo.channel(`call.${this.user.id}`).listen("StartCall", (e) => {
			this.caller = e.caller;
			this.offerData = e.data;
			this.ModelShow = true;
		});
	},
};
</script>


<style  scoped>
.adjust-margin {
	margin-left: -3px;
}
</style>