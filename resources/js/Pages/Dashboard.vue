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
							<li class="bg-gray-500 h-16 w-16  m-4  rounded-full text-white relative cursor-pointer">
								<span class="absolute top-3 left-6 font-bold text-3xl">K</span>
							</li>
							<li class="bg-gray-500 h-16 w-16  m-4  rounded-full text-white relative cursor-pointer">
								<span class="absolute top-3 left-6 font-bold text-3xl">K</span>
							</li>
							<li class="bg-gray-500 h-16 w-16  m-4  rounded-full text-white relative cursor-pointer">
								<span class="absolute top-3 left-6 font-bold text-3xl">K</span>
							</li>
							<li class="bg-gray-500 h-16 w-16  m-4  rounded-full text-white relative cursor-pointer">
								<span class="absolute top-3 left-6 font-bold text-3xl">K</span>
							</li>
							<li class="bg-gray-500 h-16 w-16  m-4  rounded-full text-white relative cursor-pointer">
								<span class="absolute top-3 left-6 font-bold text-3xl">K</span>
							</li>
							<li class="bg-gray-500 h-16 w-16  m-4  rounded-full text-white relative cursor-pointer">
								<span class="absolute top-3 left-6 font-bold text-3xl">K</span>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";

export default {
	components: {
		AppLayout,
	},
};
</script>
